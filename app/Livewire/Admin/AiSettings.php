<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Services\RbacService;
use Livewire\Component;

class AiSettings extends Component
{
    public bool $preeditEn = true;
    public bool $preeditBn = true;
    public bool $autoPublish = false;
    public array $autoCats = [];
    public int $monthlyCap = 500000;
    public string $stylePrompt = '';
    public bool $killed = false;
    public string $model = 'openai:gpt-4o';

    public function mount(): void
    {
        $s = Setting::where('key','ai.desk')->first();
        if($s){
            $v=$s->value;
            $this->preeditEn=$v['preeditEn']??true;
            $this->preeditBn=$v['preeditBn']??true;
            $this->autoPublish=$v['autoPublish']??false;
            $this->autoCats=$v['autoCats']??[];
            $this->monthlyCap=$v['monthlyCap']??500000;
            $this->stylePrompt=$v['stylePrompt']??'';
            $this->killed=$v['killed']??false;
            $this->model=$v['model']??'openai:gpt-4o';
        }
    }

    public function save(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(),'settings','edit');
        Setting::updateOrCreate(['key'=>'ai.desk'],['value'=>[
            'preeditEn'=>$this->preeditEn,'preeditBn'=>$this->preeditBn,'autoPublish'=>$this->autoPublish,'autoCats'=>$this->autoCats,'monthlyCap'=>$this->monthlyCap,'stylePrompt'=>$this->stylePrompt,'killed'=>$this->killed,'model'=>$this->model,
        ],'updated_by'=>auth()->id(),'updated_at'=>now()]);
        $this->dispatch('toast', message: $this->killed ? 'AI kill switch ON — all LLM calls blocked' : 'AI settings saved');
    }

    public function toggleKill(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(),'settings','edit');
        $this->killed=!$this->killed;
        $this->save($rbac);
    }

    public function render()
    {
        return view('livewire.admin.ai-settings');
    }
}
