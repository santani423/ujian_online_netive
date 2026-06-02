// assets/js/app.js
// Aplikasi-specific JavaScript

// Timer ujian
class ExamTimer {
    constructor(duration, onUpdate, onComplete) {
        this.duration = duration;
        this.remainingTime = duration;
        this.onUpdate = onUpdate;
        this.onComplete = onComplete;
        this.interval = null;
        this.isRunning = false;
    }

    start() {
        if (this.isRunning) return;
        
        this.isRunning = true;
        this.interval = setInterval(() => {
            this.remainingTime--;
            
            if (this.onUpdate) {
                this.onUpdate(this.remainingTime);
            }
            
            if (this.remainingTime <= 0) {
                this.stop();
                if (this.onComplete) {
                    this.onComplete();
                }
            }
        }, 1000);
    }

    stop() {
        this.isRunning = false;
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    }

    getFormattedTime() {
        const hours = Math.floor(this.remainingTime / 3600);
        const minutes = Math.floor((this.remainingTime % 3600) / 60);
        const seconds = this.remainingTime % 60;
        
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
}

// File upload handler
class FileUploadHandler {
    constructor(inputId, previewId, maxSize = 5 * 1024 * 1024) {
        this.input = document.getElementById(inputId);
        this.preview = document.getElementById(previewId);
        this.maxSize = maxSize;
        
        if (this.input) {
            this.init();
        }
    }

    init() {
        this.input.addEventListener('change', (e) => {
            this.handleFileSelect(e);
        });
    }

    handleFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Check file size
        if (file.size > this.maxSize) {
            alert(`File terlalu besar. Maksimal ${this.maxSize / 1024 / 1024}MB`);
            this.input.value = '';
            return;
        }

        // Preview image jika file adalah gambar
        if (file.type.startsWith('image/') && this.preview) {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.preview.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="max-height: 200px;">`;
            };
            reader.readAsDataURL(file);
        }
    }
}

// Search and filter
class DataTableFilter {
    constructor(tableId, searchId) {
        this.table = document.getElementById(tableId);
        this.search = document.getElementById(searchId);
        
        if (this.table && this.search) {
            this.init();
        }
    }

    init() {
        this.search.addEventListener('input', (e) => {
            this.filterTable(e.target.value);
        });
    }

    filterTable(searchTerm) {
        const rows = this.table.querySelectorAll('tbody tr');
        const term = searchTerm.toLowerCase();

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    }
}

// Export functionality
class DataExporter {
    static exportToCSV(data, filename) {
        const csvContent = "data:text/csv;charset=utf-8," 
            + data.map(row => row.join(",")).join("\n");
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    static exportToPDF(elementId, filename) {
        // Implement PDF export menggunakan jsPDF
        console.log('Export to PDF:', elementId, filename);
        // Di sini bisa diintegrasikan dengan library jsPDF
    }
}

// Inisialisasi komponen ketika DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Auto init file upload handlers
    const fileUploads = document.querySelectorAll('input[type="file"]');
    fileUploads.forEach(input => {
        const previewId = input.dataset.preview;
        if (previewId) {
            new FileUploadHandler(input.id, previewId);
        }
    });

    // Auto init table filters
    const tables = document.querySelectorAll('table[data-filter="true"]');
    tables.forEach(table => {
        const searchId = table.dataset.search;
        if (searchId) {
            new DataTableFilter(table.id, searchId);
        }
    });

    // Auto init tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto init popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    const popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});