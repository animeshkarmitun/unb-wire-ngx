  <!-- ============ AI SUGGESTION DRAWER ============ -->
  <div class="ai-drawer {{ $aiPack ? 'open' : '' }}" id="aiDrawer">
    <div class="aid-head">
      <div>
        <div class="aid-title">✦ AI Editorial Assistant</div>
        <div class="aid-sub" id="aidSub">Suggestions only — apply per card, or discard freely</div>
      </div>
      <button type="button" class="aid-close" id="aidClose" wire:click="closeAiDrawer" title="Close">✕</button>
    </div>
    <div class="aid-body" id="aidBody">
      @if($aiPack)
        {{-- Headline card --}}
        @if(!empty($aiPack['headline']))
          <div class="aid-card" id="aidCardHeadline">
            <div class="aid-card-t">Headline suggestion</div>
            <div class="aid-var">
              <p>{{ $aiPack['headline'] }}</p>
              <button type="button" class="aid-use" wire:click="applyAi('headline')" id="aidApplyHeadline">Apply</button>
            </div>
          </div>
        @endif

        {{-- Brief card --}}
        @if(!empty($aiPack['brief']))
          <div class="aid-card" id="aidCardBrief">
            <div class="aid-card-t">Brief / Intro suggestion</div>
            <div class="aid-var">
              <p class="plain">{{ $aiPack['brief'] }}</p>
              <button type="button" class="aid-use" wire:click="applyAi('brief')" id="aidApplyBrief">Apply</button>
            </div>
          </div>
        @endif

        {{-- Category suggestion --}}
        @if(!empty($aiPack['category']['name']))
          <div class="aid-card" id="aidCardCategory">
            <div class="aid-card-t">Category suggestion</div>
            <div style="font-size:14px;font-weight:700">{{ $aiPack['category']['name'] }}</div>
            <div style="margin-top:9px;text-align:right">
              <button type="button" class="aid-use" wire:click="applyAi('category')" id="aidApplyCategory">Apply category</button>
            </div>
          </div>
        @endif

        {{-- Tags suggestion --}}
        @if(!empty($aiPack['tags']))
          <div class="aid-card" id="aidCardTags">
            <div class="aid-card-t">Suggested tags</div>
            <div class="aid-tags" style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:8px">
              @foreach($aiPack['tags'] as $tag)
                <span class="aid-tag" style="background:#f3f0ff;color:#7c3aed;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600">#{{ $tag }}</span>
              @endforeach
            </div>
            <div style="text-align:right">
              <button type="button" class="aid-use all" wire:click="applyAi('tags')" id="aidApplyTags">Add all tags</button>
            </div>
          </div>
        @endif

        {{-- Body rewrite --}}
        @if(!empty($aiPack['body']))
          <div class="aid-card" id="aidCardBody">
            <div class="aid-card-t">Body rewrite proposal</div>
            <div class="aid-var" style="font-size:12.5px;color:#4b4e5c">
              {!! \Illuminate\Support\Str::limit(strip_tags($aiPack['body']), 120) !!}
            </div>
            <div style="margin-top:9px;text-align:right">
              <button type="button" class="aid-use" wire:click="applyAi('body')" id="aidApplyBody">Apply body</button>
            </div>
          </div>
        @endif

        @if(!empty($aiPack['style_lint']))
          <div class="aid-card" id="aidStyleLint" style="border-color:#d97706;background:#fffbeb">
            <div class="aid-card-t" style="color:#92400e">Style lint — {{ count($aiPack['style_lint']) }} issue(s)</div>
            <ul style="margin:6px 0 0 16px;font-size:12px;color:#92400e">
              @foreach($aiPack['style_lint'] as $v)
                <li>[{{ $v['severity'] }}] {{ $v['message'] }}</li>
              @endforeach
            </ul>
          </div>
        @endif
      @endif
    </div>
    <div class="aid-foot">
      <span>UNB Newsroom AI Guardrails Active</span>
      <span class="aid-demo" id="aidTokens">Tokens audited</span>
    </div>
  </div>
