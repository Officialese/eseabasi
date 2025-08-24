/**
 * Eseabasi Inventory Frontend JavaScript
 */

(function($) {
    'use strict';
    
    // Global variables
    let isSubmitting = false;
    let autoSaveTimer = null;
    let currentValues = {};
    
    $(document).ready(function() {
        initForms();
        initHistoryFilters();
        initRealTimeUpdates();
        initValidation();
        initExport();
        startTimeUpdates();
    });
    
    /**
     * Initialize form functionality
     */
    function initForms() {
        // Import form
        $('#import-form').on('submit', handleImportSubmit);
        
        // Stock form
        $('#stock-form').on('submit', handleStockSubmit);
        $('#stock-form').on('input', '.opening-input, .used-input', calculateStockValues);
        
        // Chopped form
        $('#chopped-form').on('submit', handleChoppedSubmit);
        $('#chopped-form').on('input', '.opening-input, .prepared-input, .packs-input', calculateChoppedValues);
        
        // Reset buttons
        $('.btn[id$="reset-form"]').on('click', handleFormReset);
        
        // Auto-save functionality
        $('.eseabasi-form input[type="number"], .eseabasi-form textarea').on('input', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                autoSaveFormData();
            }, 2000);
        });
        
        // Load saved form data
        loadSavedFormData();
    }
    
    /**
     * Initialize history filters
     */
    function initHistoryFilters() {
        $('.filter-btn').on('click', handleHistoryFilter);
        $('.reset-filter-btn').on('click', handleFilterReset);
        $('.export-btn').on('click', handleExport);
        
        // Auto-filter on enter key
        $('.filter-input').on('keypress', function(e) {
            if (e.which === 13) {
                handleHistoryFilter();
            }
        });
    }
    
    /**
     * Initialize real-time updates
     */
    function initRealTimeUpdates() {
        // Poll for current values every 30 seconds
        setInterval(function() {
            updateCurrentValues();
        }, 30000);
        
        // Update current values on page load
        updateCurrentValues();
    }
    
    /**
     * Initialize form validation
     */
    function initValidation() {
        $('.eseabasi-form input[type="number"]').on('blur', validateNumberInput);
        $('.eseabasi-form input[type="number"]').on('input', function() {
            clearInputError($(this));
        });
    }
    
    /**
     * Initialize export functionality
     */
    function initExport() {
        $('.export-btn').on('click', function() {
            const type = $(this).data('type');
            exportHistory(type, 'csv');
        });
    }
    
    /**
     * Start real-time clock updates
     */
    function startTimeUpdates() {
        updateTime();
        setInterval(updateTime, 1000);
    }
    
    /**
     * Update current time display
     */
    function updateTime() {
        const now = new Date();
        const lagosTime = new Intl.DateTimeFormat('en-US', {
            timeZone: 'Africa/Lagos',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        }).format(now);
        
        $('#current-time').text(lagosTime);
    }
    
    /**
     * Handle import form submission
     */
    function handleImportSubmit(e) {
        e.preventDefault();
        
        if (isSubmitting) return;
        
        const formData = $(this).serialize();
        const hasData = validateImportData(formData);
        
        if (!hasData) {
            showMessage('error', eseabasi_frontend.strings.required_field);
            return;
        }
        
        if (!confirm(eseabasi_frontend.strings.confirm_submit)) {
            return;
        }
        
        isSubmitting = true;
        showLoading();
        
        $.ajax({
            url: eseabasi_frontend.ajax_url,
            type: 'POST',
            data: formData + '&action=eseabasi_submit_import&nonce=' + eseabasi_frontend.nonce,
            success: function(response) {
                hideLoading();
                isSubmitting = false;
                
                if (response.success) {
                    showMessage('success', response.data.message || eseabasi_frontend.strings.success);
                    clearFormData();
                    updateCurrentValues();
                } else {
                    showMessage('error', response.data.message || eseabasi_frontend.strings.error);
                }
            },
            error: function() {
                hideLoading();
                isSubmitting = false;
                showMessage('error', eseabasi_frontend.strings.error);
            }
        });
    }
    
    /**
     * Handle stock form submission
     */
    function handleStockSubmit(e) {
        e.preventDefault();
        
        if (isSubmitting) return;
        
        const formData = $(this).serialize();
        
        if (!validateStockData()) {
            return;
        }
        
        if (!confirm(eseabasi_frontend.strings.confirm_submit)) {
            return;
        }
        
        isSubmitting = true;
        showLoading();
        
        $.ajax({
            url: eseabasi_frontend.ajax_url,
            type: 'POST',
            data: formData + '&action=eseabasi_submit_stock&nonce=' + eseabasi_frontend.nonce,
            success: function(response) {
                hideLoading();
                isSubmitting = false;
                
                if (response.success) {
                    showMessage('success', response.data.message || eseabasi_frontend.strings.success);
                    updateCurrentValues();
                } else {
                    showMessage('error', response.data.message || eseabasi_frontend.strings.error);
                }
            },
            error: function() {
                hideLoading();
                isSubmitting = false;
                showMessage('error', eseabasi_frontend.strings.error);
            }
        });
    }
    
    /**
     * Handle chopped form submission
     */
    function handleChoppedSubmit(e) {
        e.preventDefault();
        
        if (isSubmitting) return;
        
        const formData = $(this).serialize();
        
        if (!validateChoppedData()) {
            return;
        }
        
        if (!confirm(eseabasi_frontend.strings.confirm_submit)) {
            return;
        }
        
        isSubmitting = true;
        showLoading();
        
        $.ajax({
            url: eseabasi_frontend.ajax_url,
            type: 'POST',
            data: formData + '&action=eseabasi_submit_chopped&nonce=' + eseabasi_frontend.nonce,
            success: function(response) {
                hideLoading();
                isSubmitting = false;
                
                if (response.success) {
                    showMessage('success', response.data.message || eseabasi_frontend.strings.success);
                    updateCurrentValues();
                } else {
                    showMessage('error', response.data.message || eseabasi_frontend.strings.error);
                }
            },
            error: function() {
                hideLoading();
                isSubmitting = false;
                showMessage('error', eseabasi_frontend.strings.error);
            }
        });
    }
    
    /**
     * Calculate stock values in real-time
     */
    function calculateStockValues() {
        const row = $(this).closest('tr');
        const opening = parseFloat(row.find('.opening-input').val()) || 0;
        const added = parseFloat(row.find('.added-input').val()) || 0;
        const used = parseFloat(row.find('.used-input').val()) || 0;
        
        const closing = opening + added - used;
        row.find('.closing-input').val(closing.toFixed(2));
        
        // Highlight the updated field
        row.find('.closing-input').addClass('updated-field');
        setTimeout(() => {
            row.find('.closing-input').removeClass('updated-field');
        }, 2000);
    }
    
    /**
     * Calculate chopped values in real-time
     */
    function calculateChoppedValues() {
        const row = $(this).closest('tr');
        const opening = parseFloat(row.find('.opening-input').val()) || 0;
        const import_whole = parseFloat(row.find('.import-input').val()) || 0;
        const prepared = parseFloat(row.find('.prepared-input').val()) || 0;
        
        const closing = opening + import_whole - prepared;
        row.find('.closing-input').val(closing.toFixed(2));
        
        // Highlight the updated field
        row.find('.closing-input').addClass('updated-field');
        setTimeout(() => {
            row.find('.closing-input').removeClass('updated-field');
        }, 2000);
    }
    
    /**
     * Validate import form data
     */
    function validateImportData(formData) {
        let hasData = false;
        const quantities = $('#import-form .quantity-input');
        
        quantities.each(function() {
            const value = parseFloat($(this).val());
            if (value && value > 0) {
                hasData = true;
                return false; // Break loop
            }
        });
        
        return hasData;
    }
    
    /**
     * Validate stock form data
     */
    function validateStockData() {
        let isValid = true;
        
        $('#stock-form input[type="number"]').each(function() {
            if (!validateNumberInput.call(this)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    /**
     * Validate chopped form data
     */
    function validateChoppedData() {
        let isValid = true;
        
        $('#chopped-form input[type="number"]').each(function() {
            if (!validateNumberInput.call(this)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    /**
     * Validate number input
     */
    function validateNumberInput() {
        const $input = $(this);
        const value = $input.val();
        
        if (value && (isNaN(value) || parseFloat(value) < 0)) {
            showInputError($input, eseabasi_frontend.strings.invalid_number);
            return false;
        }
        
        clearInputError($input);
        return true;
    }
    
    /**
     * Show input error
     */
    function showInputError($input, message) {
        $input.addClass('input-error');
        
        // Remove existing error message
        $input.siblings('.error-message').remove();
        
        // Add error message
        $input.after('<div class="error-message" style="color: var(--eseabasi-danger); font-size: 0.8em; margin-top: 4px;">' + message + '</div>');
    }
    
    /**
     * Clear input error
     */
    function clearInputError($input) {
        $input.removeClass('input-error');
        $input.siblings('.error-message').remove();
    }
    
    /**
     * Handle form reset
     */
    function handleFormReset() {
        const form = $(this).closest('.eseabasi-form');
        
        if (confirm('Are you sure you want to reset the form? All unsaved data will be lost.')) {
            form[0].reset();
            form.find('.closing-input').val('0.00');
            clearFormErrors(form);
            clearFormData();
            updateCurrentValues();
        }
    }
    
    /**
     * Clear form errors
     */
    function clearFormErrors(form) {
        form.find('.input-error').removeClass('input-error');
        form.find('.error-message').remove();
    }
    
    /**
     * Handle history filtering
     */
    function handleHistoryFilter() {
        const container = $(this).closest('.eseabasi-history-container');
        const dateFrom = container.find('#date-from').val();
        const dateTo = container.find('#date-to').val();
        const productId = container.find('#product-filter').val();
        const type = container.find('.export-btn').data('type');
        
        showLoading();
        
        $.ajax({
            url: eseabasi_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'eseabasi_filter_history',
                type: type,
                date_from: dateFrom,
                date_to: dateTo,
                product_id: productId,
                nonce: eseabasi_frontend.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    container.find('.history-content').html(response.data.html);
                } else {
                    showMessage('error', response.data.message || eseabasi_frontend.strings.error);
                }
            },
            error: function() {
                hideLoading();
                showMessage('error', eseabasi_frontend.strings.error);
            }
        });
    }
    
    /**
     * Handle filter reset
     */
    function handleFilterReset() {
        const container = $(this).closest('.eseabasi-history-container');
        container.find('.filter-input').val('');
        container.find('.filter-btn').click();
    }
    
    /**
     * Handle export
     */
    function handleExport() {
        const type = $(this).data('type');
        exportHistory(type, 'csv');
    }
    
    /**
     * Export history data
     */
    function exportHistory(type, format) {
        const container = $('.eseabasi-history-container');
        const dateFrom = container.find('#date-from').val();
        const dateTo = container.find('#date-to').val();
        const productId = container.find('#product-filter').val();
        
        showLoading();
        
        $.ajax({
            url: eseabasi_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'eseabasi_export_history',
                type: type,
                format: format,
                date_from: dateFrom,
                date_to: dateTo,
                product_id: productId,
                nonce: eseabasi_frontend.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    // Create download link
                    const blob = new Blob([response.data.content], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename || (type + '_history.csv');
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    showMessage('success', 'Export completed successfully.');
                } else {
                    showMessage('error', response.data.message || eseabasi_frontend.strings.error);
                }
            },
            error: function() {
                hideLoading();
                showMessage('error', eseabasi_frontend.strings.error);
            }
        });
    }
    
    /**
     * Update current values from server
     */
    function updateCurrentValues() {
        const stockForm = $('#stock-form');
        const choppedForm = $('#chopped-form');
        
        if (stockForm.length) {
            updateFormValues('stock');
        }
        
        if (choppedForm.length) {
            updateFormValues('chopped');
        }
    }
    
    /**
     * Update form values
     */
    function updateFormValues(formType) {
        const currentDate = $('#current-date').text();
        
        $.ajax({
            url: eseabasi_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'eseabasi_get_current_values',
                form_type: formType,
                date: currentDate,
                nonce: eseabasi_frontend.nonce
            },
            success: function(response) {
                if (response.success && response.data.values) {
                    updateFormWithValues(formType, response.data.values);
                }
            }
        });
    }
    
    /**
     * Update form with values from server
     */
    function updateFormWithValues(formType, values) {
        const form = $('#' + formType + '-form');
        
        $.each(values, function(productId, data) {
            const row = form.find('tr[data-product-id="' + productId + '"]');
            
            if (row.length) {
                if (formType === 'stock') {
                    row.find('.opening-input').val(data.opening_value);
                    row.find('.added-input').val(data.added_value);
                    row.find('.used-input').val(data.used_value);
                    row.find('.closing-input').val(data.closing_value);
                } else if (formType === 'chopped') {
                    row.find('.opening-input').val(data.opening_value);
                    row.find('.import-input').val(data.import_value);
                    row.find('.prepared-input').val(data.prepared_value);
                    row.find('.closing-input').val(data.closing_value);
                    row.find('.packs-input').val(data.packs_gotten_value);
                }
            }
        });
    }
    
    /**
     * Auto-save form data to localStorage
     */
    function autoSaveFormData() {
        const formData = {};
        
        $('.eseabasi-form').each(function() {
            const formId = $(this).attr('id');
            formData[formId] = $(this).serializeArray();
        });
        
        localStorage.setItem('eseabasi_form_data', JSON.stringify(formData));
    }
    
    /**
     * Load saved form data from localStorage
     */
    function loadSavedFormData() {
        const savedData = localStorage.getItem('eseabasi_form_data');
        
        if (savedData) {
            try {
                const formData = JSON.parse(savedData);
                
                $.each(formData, function(formId, data) {
                    const form = $('#' + formId);
                    
                    $.each(data, function(index, field) {
                        const input = form.find('[name="' + field.name + '"]');
                        if (input.length && !input.hasClass('readonly')) {
                            input.val(field.value);
                        }
                    });
                });
            } catch (e) {
                console.warn('Failed to load saved form data:', e);
            }
        }
    }
    
    /**
     * Clear saved form data
     */
    function clearFormData() {
        localStorage.removeItem('eseabasi_form_data');
    }
    
    /**
     * Show loading indicator
     */
    function showLoading() {
        if ($('.loading-overlay').length === 0) {
            $('body').append('<div class="loading-overlay"><div class="eseabasi-loading"></div></div>');
        }
    }
    
    /**
     * Hide loading indicator
     */
    function hideLoading() {
        $('.loading-overlay').remove();
    }
    
    /**
     * Show message
     */
    function showMessage(type, message) {
        const messageContainer = $('.form-messages').length ? $('.form-messages') : $('.history-content');
        
        // Remove existing messages
        messageContainer.find('.eseabasi-message').remove();
        
        // Add new message
        const messageHtml = '<div class="eseabasi-message ' + type + '">' + message + '</div>';
        messageContainer.prepend(messageHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            messageContainer.find('.eseabasi-message').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: messageContainer.offset().top - 100
        }, 500);
    }
    
    /**
     * Keyboard shortcuts
     */
    $(document).on('keydown', function(e) {
        // Ctrl+S to save
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const activeForm = $('.eseabasi-form:visible');
            if (activeForm.length) {
                activeForm.find('button[type="submit"]').click();
            }
        }
        
        // Escape to close messages
        if (e.key === 'Escape') {
            $('.eseabasi-message').fadeOut();
        }
    });
    
    /**
     * Form field focus management
     */
    $('.eseabasi-table input[type="number"]').on('focus', function() {
        $(this).select();
    });
    
    /**
     * Auto-format numbers
     */
    $('.eseabasi-table input[type="number"]').on('blur', function() {
        const value = parseFloat($(this).val());
        if (!isNaN(value)) {
            $(this).val(value.toFixed(2));
        }
    });
    
    /**
     * Mobile-specific optimizations
     */
    if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        // Prevent zoom on input focus
        $('input[type="number"]').attr('maxlength', '10');
        
        // Add mobile-specific classes
        $('body').addClass('mobile-device');
        
        // Optimize table scrolling
        $('.form-table-wrapper').css({
            '-webkit-overflow-scrolling': 'touch',
            'overflow-x': 'auto'
        });
    }
    
})(jQuery);