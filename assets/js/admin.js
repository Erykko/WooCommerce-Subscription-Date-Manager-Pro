/**
 * WooCommerce Subscription Date Manager Pro
 * Admin JavaScript
 */

(function($) {
    'use strict';

    // Main class for handling subscription updates
    class SubscriptionDateManager {
        constructor() {
            this.form = $('#wcsm-update-form');
            this.submitButton = $('#wcsm-update-button');
            this.progressBar = $('.wcsm-progress');
            this.resultsContainer = $('#wcsm-results');
            this.stats = {
                updated: $('#wcsm-stat-updated'),
                skipped: $('#wcsm-stat-skipped'),
                errors: $('#wcsm-stat-errors')
            };
            
            this.init();
        }

        init() {
            this.initDatePickers();
            this.initFormHandler();
        }

        initDatePickers() {
            $('.wcsm-date-input').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: new Date(),
                changeMonth: true,
                changeYear: true
            });
        }

        initFormHandler() {
            this.form.on('submit', (e) => {
                e.preventDefault();
                this.processUpdate();
            });
        }

        async processUpdate() {
            if (!this.validateForm()) {
                return;
            }

            this.setLoadingState(true);
            
            try {
                const formData = new FormData(this.form[0]);
                formData.append('action', 'wcsm_update_dates');
                formData.append('nonce', wcsmData.nonce);

                const response = await this.sendRequest(formData);
                this.handleResponse(response);
            } catch (error) {
                this.handleError(error);
            } finally {
                this.setLoadingState(false);
            }
        }

        validateForm() {
            const newDate = $('#wcsm-new-date').val();
            const excludeAfter = $('#wcsm-exclude-after').val();

            if (!newDate || !excludeAfter) {
                this.showError('Please fill in all required fields.');
                return false;
            }

            if (new Date(newDate) <= new Date(excludeAfter)) {
                this.showError('New payment date must be after the exclusion date.');
                return false;
            }

            return true;
        }

        async sendRequest(formData) {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            return await response.json();
        }

        handleResponse(response) {
            if (response.success) {
                this.updateStats(response.data.stats);
                this.showResults(response.data.message, 'success');
            } else {
                this.showError(response.data.message);
            }
        }

        handleError(error) {
            console.error('Update error:', error);
            this.showError('An error occurred while processing the update.');
        }

        updateStats(stats) {
            Object.keys(this.stats).forEach(key => {
                if (stats[key] !== undefined) {
                    this.stats[key].text(stats[key]);
                }
            });
        }

        showResults(message, type = 'success') {
            const className = `notice notice-${type}`;
            this.resultsContainer
                .removeClass()
                .addClass(className)
                .html(`<p>${message}</p>`)
                .show();
        }

        showError(message) {
            this.showResults(message, 'error');
        }

        setLoadingState(loading) {
            this.submitButton.prop('disabled', loading);
            this.form.toggleClass('wcsm-loading', loading);
            this.progressBar.toggle(loading);
        }

        updateProgress(processed, total) {
            const percentage = Math.round((processed / total) * 100);
            $('.wcsm-progress-bar-inner').css('width', percentage + '%');
            $('.wcsm-progress-text').text(`Processing... ${percentage}%`);
        }
    }

    // Initialize on document ready
    $(document).ready(() => {
        if ($('#wcsm-update-form').length) {
            window.wcsmManager = new SubscriptionDateManager();
        }
    });

})(jQuery);