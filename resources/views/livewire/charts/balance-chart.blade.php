<div>
    <div class="flex justify-end gap-1 mb-2">
        @foreach (['30d' => '30D', '90d' => '90D', 'ytd' => 'YTD', 'all' => 'All'] as $key => $label)
            <x-button :label="$label" class="btn-xs {{ $range === $key ? 'btn-primary' : 'btn-ghost' }}" wire:click="setRange('{{ $key }}')" />
        @endforeach
    </div>
    <x-chart wire:model="chart" />
</div>
