function printReport() {
            window.print();
        }

        // Auto-submit form when date changes
        document.getElementById('date').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });