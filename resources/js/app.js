import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
window.Chart = ApexCharts; // MaryUI's <x-chart> references the global as `Chart`

// Re-apply theme after Livewire SPA navigation
document.addEventListener('livewire:navigated', () => {
    const theme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', theme);
});
