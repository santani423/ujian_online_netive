// assets/js/chart.js
class ChartManager {
    constructor() {
        this.charts = new Map();
    }

    initChart(canvasId, type, data, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        };

        const mergedOptions = { ...defaultOptions, ...options };

        const chart = new Chart(ctx, {
            type: type,
            data: data,
            options: mergedOptions
        });

        this.charts.set(canvasId, chart);
        return chart;
    }

    // Chart statistik untuk dashboard guru
    initGuruStats() {
        const ctx = document.getElementById('statsChart');
        if (!ctx) return;

        const data = {
            labels: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
            datasets: [
                {
                    label: 'Ujian Dibuat',
                    data: [12, 19, 3, 5, 2, 3],
                    backgroundColor: 'rgba(102, 126, 234, 0.2)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2,
                    tension: 0.4
                },
                {
                    label: 'Siswa Mengikuti',
                    data: [8, 15, 12, 10, 7, 9],
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 2,
                    tension: 0.4
                }
            ]
        };

        this.initChart('statsChart', 'line', data);
    }

    // Chart pie untuk distribusi nilai
    initNilaiDistribution(data) {
        const chartData = {
            labels: ['A (90-100)', 'B (80-89)', 'C (70-79)', 'D (60-69)', 'E (<60)'],
            datasets: [{
                data: data || [15, 20, 25, 18, 12],
                backgroundColor: [
                    '#28a745',
                    '#20c997',
                    '#ffc107',
                    '#fd7e14',
                    '#dc3545'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        };

        this.initChart('nilaiChart', 'pie', chartData, {
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        });
    }

    // Destroy chart
    destroyChart(canvasId) {
        if (this.charts.has(canvasId)) {
            this.charts.get(canvasId).destroy();
            this.charts.delete(canvasId);
        }
    }

    // Update chart data
    updateChart(canvasId, newData) {
        if (this.charts.has(canvasId)) {
            const chart = this.charts.get(canvasId);
            chart.data = newData;
            chart.update();
        }
    }
}

// Inisialisasi chart manager
document.addEventListener('DOMContentLoaded', function() {
    window.chartManager = new ChartManager();
    
    // Auto init charts jika ada
    if (document.getElementById('statsChart')) {
        window.chartManager.initGuruStats();
    }
    
    if (document.getElementById('nilaiChart')) {
        window.chartManager.initNilaiDistribution();
    }
});