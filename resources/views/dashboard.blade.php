<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4">
        <div>
            <livewire:pages::charts.balance-chart :account-id="null" key="chart-household" />
        </div>

        <div>
            <h2 class="text-lg font-semibold mb-3">{{ __('Accounts') }}</h2>
            <livewire:pages::accounts.index key="dashboard-accounts" />
        </div>
    </div>
</x-layouts::app>
