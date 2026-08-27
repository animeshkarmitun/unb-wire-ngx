<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use Livewire\Component;

class DeliverySettings extends Component
{
    public int $retryAttempts=3; public int $backoffSeconds=60; public int $autoPauseAfter=5; public bool $atLeastOnce=true;
    public function mount(): void {
        $s=Setting::where('key','delivery')->first();
        if($s){ $v=is_string($s->value)?json_decode($s->value,true):$s->value; $this->retryAttempts=$v['retryAttempts']??3; $this->backoffSeconds=$v['backoffSeconds']??60; $this->autoPauseAfter=$v['autoPauseAfter']??5; $this->atLeastOnce=$v['atLeastOnce']??true; }
    }
    public function save(): void {
        Setting::updateOrCreate(['key'=>'delivery'],['value'=>['retryAttempts'=>$this->retryAttempts,'backoffSeconds'=>$this->backoffSeconds,'autoPauseAfter'=>$this->autoPauseAfter,'atLeastOnce'=>$this->atLeastOnce],'updated_by'=>auth()->id(),'updated_at'=>now()]);
        $this->dispatch('toast', message:'Delivery settings saved');
    }
    public function render(){ return view('livewire.admin.delivery-settings'); }
}
