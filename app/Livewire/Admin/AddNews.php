<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Story;
use App\Services\AiService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AddNews extends Component
{
    public int $step = 1;
    public ?int $storyId = null;
    public string $language = 'en';
    public string $headline = '';
    public string $subHead = '';
    public string $brief = '';
    public string $bodyHtml = '';
    public string $categoryId = '';
    public string $subCategoryId = '';
    public string $datelineCity = '';
    public string $datelineAt = '';
    public string $priority = 'routine';
    public bool $isBreaking = false;
    public string $embargoUntil = '';
    public array $aiTouched = [];
    public array $selectedMediaIds = [];
    public ?array $aiPack = null;
    public bool $aiLoading = false;

    public function mount(?int $id = null): void
    {
        if($id){
            $s=Story::with('tags')->findOrFail($id);
            $this->storyId=$s->id; $this->language=$s->language; $this->headline=$s->headline; $this->subHead=$s->sub_head??''; $this->brief=$s->brief; $this->bodyHtml=$s->body_html; $this->categoryId=(string)$s->category_id; $this->subCategoryId=(string)($s->sub_category_id??''); $this->datelineCity=$s->dateline_city??''; $this->priority=$s->priority; $this->isBreaking=$s->is_breaking; $this->aiTouched=$s->ai_touched??[];
            $this->selectedMediaIds = DB::table('story_media')->where('story_id',$s->id)->pluck('asset_id')->map(fn($v)=>(int)$v)->toArray();
        }
    }

    public function next(): void { $this->validateStep(); if($this->step<4) $this->step++; $this->autosave(); }
    public function prev(): void { if($this->step>1) $this->step--; }
    public function go(int $s): void { $this->step=$s; }

    private function validateStep(): void {
        if($this->step===1){
            $this->validate(['headline'=>'required|max:300','brief'=>'required|max:280','categoryId'=>'required|exists:categories,id']);
        }
        if($this->step===2){
            $this->validate(['bodyHtml'=>'required|min:20']);
        }
    }

    public function updatedBodyHtml(): void { if(isset($this->aiTouched['body'])) unset($this->aiTouched['body']); }
    public function updatedHeadline(): void { if(isset($this->aiTouched['headline'])) unset($this->aiTouched['headline']); }

    public function syncBody(string $html): void { $this->bodyHtml=$html; if(isset($this->aiTouched['body'])) unset($this->aiTouched['body']); }

    public function toggleMedia(int $id): void {
        if(in_array($id,$this->selectedMediaIds)) $this->selectedMediaIds=array_values(array_diff($this->selectedMediaIds,[$id]));
        else $this->selectedMediaIds[]=$id;
        $this->autosave();
    }

    public function callAi(string $kind, AiService $svc): void {
        $this->aiLoading=true;
        $payload=['headline'=>$this->headline,'brief'=>$this->brief,'text'=>strip_tags($this->bodyHtml) ?: $this->brief,'category'=>$this->categoryId];
        $pack=$svc->call($kind, $payload, auth()->id(), $this->storyId);
        if(isset($pack['error'])){ $this->dispatch('toast', message:$pack['error']); $this->aiLoading=false; return; }
        $this->aiPack=$pack;
        $this->aiLoading=false;
        $this->dispatch('toast', message:'AI suggestion ready');
    }

    public function applyAi(string $field): void {
        if(!$this->aiPack) return;
        if($field==='headline' && isset($this->aiPack['headline'])){ $this->headline=$this->aiPack['headline']; $this->aiTouched['headline']=true; }
        if($field==='brief' && isset($this->aiPack['brief'])){ $this->brief=$this->aiPack['brief']; $this->aiTouched['brief']=true; }
        if($field==='body' && isset($this->aiPack['body'])){ $this->bodyHtml=$this->aiPack['body']; $this->aiTouched['body']=true; }
        if($field==='category' && isset($this->aiPack['category']['name'])){
            $cat=Category::where('name_en','ilike',$this->aiPack['category']['name'])->first();
            if($cat) $this->categoryId=(string)$cat->id;
            $this->aiTouched['category']=true;
        }
        $this->dispatch('toast', message:'Applied '.$field);
    }

    public function autosave(): void {
        $data=[
            'language'=>$this->language,'headline'=>$this->headline ?: 'Untitled','sub_head'=>$this->subHead?:null,'brief'=>$this->brief ?: '—','body_html'=>$this->bodyHtml ?: '<p></p>','body_text'=>strip_tags($this->bodyHtml),'category_id'=>$this->categoryId?:Category::first()->id,'sub_category_id'=>$this->subCategoryId?:null,'dateline_city'=>$this->datelineCity?:null,'dateline_at'=>$this->datelineAt?:null,'is_breaking'=>$this->isBreaking,'priority'=>$this->priority,'embargo_until'=>$this->embargoUntil?:null,'ai_touched'=>$this->aiTouched?:null,'owner_id'=>auth()->id(),'created_by'=>auth()->id(),'status'=>'draft','version'=>1,
        ];
        DB::transaction(function() use($data){
            if($this->storyId){
                $s=Story::find($this->storyId);
                $s->update(array_merge($data,['version'=>$s->version+1]));
                $s->versions()->create(['version'=>$s->version,'snapshot'=>['headline'=>$this->headline,'brief'=>$this->brief,'body_html'=>$this->bodyHtml],'created_by'=>auth()->id(),'created_at'=>now()]);
            } else {
                $s=Story::create($data);
                $this->storyId=$s->id;
                $s->versions()->create(['version'=>1,'snapshot'=>['headline'=>$this->headline,'brief'=>$this->brief,'body_html'=>$this->bodyHtml],'created_by'=>auth()->id(),'created_at'=>now()]);
            }
            if($this->storyId){
                DB::table('story_media')->where('story_id',$this->storyId)->delete();
                foreach($this->selectedMediaIds as $idx=>$aid){
                    DB::table('story_media')->insert(['story_id'=>$this->storyId,'asset_id'=>$aid,'role'=>'inline','sort_order'=>$idx]);
                }
            }
        });
    }

    public function sendToReview(NotificationService $notifs): void {
        $this->validate(['headline'=>'required','brief'=>'required','categoryId'=>'required','bodyHtml'=>'required']);
        $this->autosave();
        $s=Story::find($this->storyId);
        DB::transaction(function() use($s){
            $s->update(['status'=>'in_review','assigned_editor_id'=>null]);
            $s->events()->create(['actor_id'=>auth()->id(),'action'=>'sent_to_review','from_status'=>'draft','to_status'=>'in_review']);
        });
        $notifs->notifyReviewRequested($s->id, $s->headline, auth()->id());
        $this->dispatch('toast', message:'Sent to review');
        $this->step=4;
    }

    public function publish(NotificationService $notifs): void {
        $s=Story::findOrFail($this->storyId);
        if(!empty($this->aiTouched) && !$this->isBreaking){
            $this->dispatch('toast', message:'AI-touched fields — review required before publish');
            return;
        }
        DB::transaction(function() use($s){
            $s->update(['status'=>'published','published_at'=>now()]);
            $s->events()->create(['actor_id'=>auth()->id(),'action'=>'published','from_status'=>$s->status,'to_status'=>'published']);
            DB::table('index_outbox')->insert(['index_name'=>'main','op'=>'upsert','document_id'=>$s->public_id,'status'=>'pending','attempts'=>0,'created_at'=>now(),'processed_at'=>null]);
        });
        dispatch(new \App\Jobs\FanoutStory($s->id));
        dispatch(new \App\Jobs\ProcessIndexOutbox());
        $notifs->notifyStatusChange($s->id, 'published', auth()->id());
        $this->dispatch('toast', message:'Story published');
    }

    public function render()
    {
        $cats=Category::whereNull('parent_id')->orderBy('sort_order')->get();
        $subs=$this->categoryId ? Category::where('parent_id',$this->categoryId)->get() : collect();
        $media=MediaAsset::where('status','library')->orderByDesc('created_at')->limit(24)->get();
        return view('livewire.admin.add-news', compact('cats','subs','media'));
    }
}
