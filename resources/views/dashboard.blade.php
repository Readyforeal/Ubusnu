<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4">
        <livewire:pages::dashboard.budget-status key="budget-status" />

        <livewire:pages::dashboard.upcoming-bills key="upcoming-bills" />

        <livewire:pages::dashboard.goal-progress key="goal-progress" />

        <div>
            <livewire:pages::charts.balance-chart :account-id="null" key="chart-household" />
        </div>

        <livewire:pages::accounts.index key="dashboard-accounts" />
    </div>
</x-layouts::app>
