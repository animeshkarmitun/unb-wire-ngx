<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Models\Package;
use App\Services\RbacService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class ClientsManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $tierFilter = 'all';
    public string $sort = 'name';

    public ?int $selectedId = null;
    public string $drawerTab = 'overview';

    public bool $showOnboard = false;
    public string $wName = '';
    public string $wEmail = '';
    public string $wType = 'newspaper';
    public string $wPackage = '';
    public bool $showPause = false;
    public bool $showDeact = false;
    public string $deactConfirm = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedTierFilter(): void { $this->resetPage(); }

    public function selectClient(int $id): void
    {
        $this->selectedId = $id;
        $this->drawerTab = 'overview';
    }

    public function closeDrawer(): void { $this->selectedId = null; }

    public function openOnboard(): void
    {
        $this->wName = ''; $this->wEmail = ''; $this->wType = 'newspaper';
        $this->wPackage = Package::where('status','active')->first()?->id ?? '';
        $this->showOnboard = true;
    }

    public function onboard(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'clients', 'create');
        $this->validate(['wName'=>'required|max:160','wEmail'=>'required|email','wPackage'=>'required|exists:packages,id']);
        DB::transaction(function(){
            $code = strtoupper(Str::slug(substr($this->wName,0,6))).rand(10,99);
            $clientId = DB::table('clients')->insertGetId([
                'public_id'=>(string)Str::ulid(),'name'=>$this->wName,'code'=>$code,'type'=>$this->wType,'country'=>'BD','timezone'=>'Asia/Dhaka','status'=>'active','billing_email'=>$this->wEmail,'created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('client_users')->insert(['client_id'=>$clientId,'name'=>'Desk','email'=>$this->wEmail,'password'=>Hash::make('password'),'client_role_id'=>DB::table('roles')->where('type','client')->value('id'),'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
            DB::table('client_channels')->insert(['client_id'=>$clientId,'type'=>'api','config'=>json_encode(['endpoint'=>'https://'.$code.'.example.com/api']),'status'=>'active','failure_count'=>0,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('client_packages')->insert(['client_id'=>$clientId,'package_id'=>$this->wPackage,'starts_at'=>now(),'status'=>'active','created_at'=>now()]);
        });
        $this->showOnboard=false;
        $this->dispatch('toast', message:'Client onboarded');
    }

    public function pause(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(),'clients','edit');
        Client::where('id',$this->selectedId)->update(['status'=>'suspended']);
        $this->showPause=false;
        $this->dispatch('toast', message:'Client paused');
    }

    public function deactivate(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(),'clients','delete');
        if($this->deactConfirm!=='DEACTIVATE'){ $this->dispatch('toast', message:'Type DEACTIVATE to confirm'); return; }
        Client::where('id',$this->selectedId)->update(['status'=>'closed']);
        $this->showDeact=false; $this->deactConfirm='';
        $this->dispatch('toast', message:'Client deactivated');
    }

    public function render()
    {
        $query = Client::with(['clientPackages.package'])->withCount('clientChannels');
        if($this->search!==''){ $s='%'.$this->search.'%'; $query->where(fn($q)=>$q->where('name','like',$s)->orWhere('code','like',$s)); }
        if($this->statusFilter!=='all'){ $map=['active'=>'active','paused'=>'suspended','deactivated'=>'closed']; $query->where('status',$map[$this->statusFilter]??$this->statusFilter); }
        if($this->tierFilter!=='all'){
            $query->whereHas('clientPackages.package', fn($q)=>$q->where('name','like','%'.$this->tierFilter.'%'));
        }
        if($this->sort==='renewal'){ $query->orderBy('created_at','desc'); } else { $query->orderBy('name'); }
        $clients = $query->paginate(12);
        $packages = Package::where('status','active')->get();
        $selected = $this->selectedId ? Client::with(['clientChannels','clientPackages.package','clientUsers'])->find($this->selectedId) : null;
        $stats = ['total'=>Client::count(),'active'=>Client::where('status','active')->count(),'paused'=>Client::where('status','suspended')->count(),'issues'=>0];
        return view('livewire.admin.clients-manager', compact('clients','packages','selected','stats'));
    }
}
