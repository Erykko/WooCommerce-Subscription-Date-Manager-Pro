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
            this.initValidation();
        }

        initDatePickers() {
            if ($.fn.datepicker) {
                $('.wcsm-date-input').datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: new Date(),
                    changeMonth: true,
                    changeYear: true,
                    showButtonPanel: true
                });
            }
        }

        initFormHandler() {
            this.form.on('submit', (e) => {
                e.preventDefault();
                this.processUpdate();
            });
        }

        initValidation() {
            // Real-time validation
            $('#wcsm-new-date, #wcsm-exclude-after').on('change', () => {
                this.validateDates();
            });

            $('#wcsm-excluded-emails').on('blur', () => {
                this.validateEmails();
            });
        }

        validateDates() {
            const newDate = $('#wcsm-new-date').val();
            const excludeAfter = $('#wcsm-exclude-after').val();
            
            if (newDate && excludeAfter) {
                if (new Date(newDate) <= new Date(excludeAfter)) {
                    this.showFieldError('#wcsm-new-date', wcsmData.i18n.dateError || 'New payment date must be after the exclusion date.');
                    return false;
                } else {
                    this.clearFieldError('#wcsm-new-date');
                    this.clearFieldError('#wcsm-exclude-after');
                }
            }
            return true;
        }

        validateEmails() {
            const emailsText = $('#wcsm-excluded-emails').val();
            if (!emailsText.trim()) return true;

            const emails = emailsText.split('\n').map(email => email.trim()).filter(email => email);
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            for (let email of emails) {
                if (!emailRegex.test(email)) {
                    this.showFieldError('#wcsm-excluded-emails', `Invalid email format: ${email}`);
                    return false;
                }
            }
            
            this.clearFieldError('#wcsm-excluded-emails');
            return true;
        }

        showFieldError(fieldSelector, message) {
            const field = $(fieldSelector);
            field.addClass('wcsm-field-error');
            
            // Remove existing error message
            field.siblings('.wcsm-error-message').remove();
            
            // Add error message
            field.after(`<span class="wcsm-error-message">${message}</span>`);
        }

        clearFieldError(fieldSelector) {
            const field = $(fieldSelector);
            field.removeClass('wcsm-field-error').addClass('wcsm-field-success');
            field.siblings('.wcsm-error-message').remove();
            
            // Remove success class after a delay
            setTimeout(() => {
                field.removeClass('wcsm-field-success');
            }, 2000);
        }

        async processUpdate() {
            if (!this.validateForm()) {
                return;
            }

            // Show confirmation dialog
            if (!confirm(wcsmData.i18n.confirmUpdate)) {
                return;
            }

            this.setLoadingState(true);
            
            try {
                const formData = new FormData(this.form[0]);
                formData.append('action', 'wcsm_update_dates');

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

            if (!this.validateDates()) {
                return false;
            }

            if (!this.validateEmails()) {
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
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (!data) {
                throw new Error('Empty response from server');
            }

            return data;
        }

        handleResponse(response) {
            if (response.success) {
                this.updateStats(response.data.stats);
                this.showResults(response.data.message, 'success');
                
                // Reset form on success
                this.resetForm();
            } else {
                this.showError(response.data.message || 'Unknown error occurred');
            }
        }

        handleError(error) {
            console.error('Update error:', error);
            this.showError(wcsmData.i18n.error || 'An error occurred while processing the update.');
        }

        updateStats(stats) {
            if (!stats) return;
            
            Object.keys(this.stats).forEach(key => {
                if (stats[key] !== undefined) {
                    this.stats[key].text(stats[key]);
                    
                    // Add animation
                    this.stats[key].parent().addClass('updated');
                    setTimeout(() => {
                        this.stats[key].parent().removeClass('updated');
                    }, 1000);
                }
            });
        }

        showResults(message, type = 'success') {
            const className = `notice notice-${type}`;
            this.resultsContainer
                .removeClass()
                .addClass(className)
                .html(`<p>${message}</p>`)
                .slideDown();

            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    this.resultsContainer.slideUp();
                }, 5000);
            }
        }

        showError(message) {
            this.showResults(message, 'error');
        }

        setLoadingState(loading) {
            this.submitButton.prop('disabled', loading);
            this.form.toggleClass('wcsm-loading', loading);
            this.progressBar.toggle(loading);
            
            if (loading) {
                this.submitButton.addClass('processing').text(wcsmData.i18n.processing || 'Processing...');
            } else {
                this.submitButton.removeClass('processing').text('Update Subscription Dates');
            }
        }

        resetForm() {
            // Clear date fields
            $('#wcsm-new-date, #wcsm-exclude-after').val('');
            
            // Clear any validation states
            $('.wcsm-field-error, .wcsm-field-success').removeClass('wcsm-field-error wcsm-field-success');
            $('.wcsm-error-message').remove();
        }

        updateProgress(processed, total) {
            if (total === 0) return;
            
            const percentage = Math.round((processed / total) * 100);
            $('.wcsm-progress-bar-inner').css('width', percentage + '%');
            $('.wcsm-progress-text').text(`${wcsmData.i18n.processing || 'Processing'}... ${percentage}%`);
        }
    }

    // Utility functions
    const utils = {
        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        },

        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialize on document ready
    $(document).ready(() => {
        if ($('#wcsm-update-form').length) {
            window.wcsmManager = new SubscriptionDateManager();
        }

        // Add tooltips if available
        if ($.fn.tooltip) {
            $('[data-tooltip]').tooltip();
        }

        // Handle settings form
        if ($('#wcsm-settings-form').length) {
            $('#wcsm-settings-form').on('submit', function(e) {
                // Add any settings-specific validation here
            });
        }
    });

    // Expose utilities globally
    window.wcsmUtils = utils;

})(jQuery);