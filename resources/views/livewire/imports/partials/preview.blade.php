<x-card class="border border-base-300">
    <div class="flex justify-between items-center mb-3 text-sm">
        <div class="space-x-3">
            <span class="text-success">{{ $counts['new'] }} new</span>
            <span class="text-warning">{{ $counts['duplicate'] }} duplicates</span>
            <span class="text-error">{{ $counts['error'] }} errors</span>
        </div>
        <x-button label="Import {{ $counts['new'] }} rows" class="btn-primary" wire:click="commit" wire:loading.attr="disabled" />
    </div>

    <div class="overflow-x-auto">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th></th>
                    <th>Date</th>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($previewRows as $i => $row)
                    <tr class="{{ $row['status'] === 'error' ? 'opacity-50' : '' }}">
                        <td>
                            @if ($row['status'] !== 'error')
                                <input type="checkbox"
                                       class="checkbox checkbox-sm"
                                       wire:click="toggleRow({{ $i }})"
                                       @checked($row['status'] === 'new') />
                            @endif
                        </td>
                        <td>{{ $row['occurred_on'] ?? '—' }}</td>
                        <td>{{ $row['description'] ?? '—' }}</td>
                        <td class="text-right font-mono">
                            @if ($row['amount_cents'] !== null)
                                {{ \App\Support\Money::format($row['amount_cents']) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($row['status'] === 'new')
                                <x-badge value="New" class="badge-success badge-sm" />
                            @elseif ($row['status'] === 'duplicate')
                                <x-badge value="Duplicate" class="badge-warning badge-sm" />
                            @else
                                <x-badge value="Error" class="badge-error badge-sm" />
                                <div class="text-xs text-error mt-1">{{ $row['error'] ?? '' }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>
