/**
 * Eseabasi Inventory Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initTabs();
        initProductManagement();
        initHistoryManagement();
    });
    
    /**
     * Initialize tab functionality
     */
    function initTabs() {
        $('.tab-link').on('click', function(e) {
            e.preventDefault();
            
            var target = $(this).attr('href');
            
            // Update active states
            $('.tab-link').removeClass('active');
            $(this).addClass('active');
            
            $('.tab-content').removeClass('active');
            $(target).addClass('active');
        });
    }
    
    /**
     * Initialize product management
     */
    function initProductManagement() {
        // Add product
        $('.add-product-btn').on('click', function() {
            var type = $(this).data('type');
            showAddProductModal(type);
        });
        
        // Edit product
        $('.edit-product-btn').on('click', function() {
            var productId = $(this).data('id');
            showEditProductModal(productId);
        });
        
        // Delete product
        $('.delete-product-btn').on('click', function() {
            var productId = $(this).data('id');
            
            if (confirm(eseabasi_ajax.strings.confirm_delete)) {
                deleteProduct(productId);
            }
        });
    }
    
    /**
     * Initialize history management
     */
    function initHistoryManagement() {
        // Clear all history
        $('.clear-history-btn').on('click', function() {
            var type = $(this).data('type');
            
            if (confirm(eseabasi_ajax.strings.confirm_clear)) {
                clearHistory(type);
            }
        });
        
        // Delete history item
        $('.delete-history-item-btn').on('click', function() {
            var itemId = $(this).data('id');
            var type = $(this).data('type');
            
            if (confirm(eseabasi_ajax.strings.confirm_delete)) {
                deleteHistoryItem(itemId, type);
            }
        });
    }
    
    /**
     * Show add product modal
     */
    function showAddProductModal(type) {
        var modal = createProductModal('add', type);
        $('body').append(modal);
        
        $('#eseabasi-product-modal').fadeIn();
        
        $('#eseabasi-product-form').on('submit', function(e) {
            e.preventDefault();
            addProduct(type, $(this).serialize());
        });
    }
    
    /**
     * Show edit product modal
     */
    function showEditProductModal(productId) {
        // Get product data first
        $.ajax({
            url: eseabasi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'eseabasi_get_product',
                product_id: productId,
                nonce: eseabasi_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var modal = createProductModal('edit', response.data.type, response.data);
                    $('body').append(modal);
                    
                    $('#eseabasi-product-modal').fadeIn();
                    
                    $('#eseabasi-product-form').on('submit', function(e) {
                        e.preventDefault();
                        editProduct(productId, $(this).serialize());
                    });
                }
            }
        });
    }
    
    /**
     * Create product modal HTML
     */
    function createProductModal(mode, type, data) {
        data = data || {};
        var title = mode === 'add' ? 'Add New Product' : 'Edit Product';
        
        var html = `
            <div id="eseabasi-product-modal" class="eseabasi-modal">
                <div class="eseabasi-modal-content">
                    <div class="eseabasi-modal-header">
                        <h3>${title}</h3>
                        <span class="eseabasi-modal-close">&times;</span>
                    </div>
                    <div class="eseabasi-modal-body">
                        <form id="eseabasi-product-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="product-name">Product Name</label>
                                    </th>
                                    <td>
                                        <input type="text" id="product-name" name="name" value="${data.name || ''}" required />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="product-is-fruit">Is Fruit?</label>
                                    </th>
                                    <td>
                                        <input type="checkbox" id="product-is-fruit" name="is_fruit" value="1" ${data.is_fruit ? 'checked' : ''} />
                                        <label for="product-is-fruit">Yes, this is a fruit</label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="product-status">Status</label>
                                    </th>
                                    <td>
                                        <select id="product-status" name="status">
                                            <option value="active" ${data.status === 'active' ? 'selected' : ''}>Active</option>
                                            <option value="inactive" ${data.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <div class="eseabasi-modal-footer">
                                <button type="submit" class="button button-primary">Save Product</button>
                                <button type="button" class="button eseabasi-modal-close">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        return html;
    }
    
    /**
     * Add product
     */
    function addProduct(type, formData) {
        showLoading();
        
        $.ajax({
            url: eseabasi_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=eseabasi_add_product&type=' + type + '&nonce=' + eseabasi_ajax.nonce,
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showNotice('success', response.data.message);
                    closeModal();
                    location.reload(); // Refresh to show new product
                } else {
                    showNotice('error', response.data.message || eseabasi_ajax.strings.error);
                }
            },
            error: function() {
                hideLoading();
                showNotice('error', eseabasi_ajax.strings.error);
            }
        });
    }
    
    /**
     * Edit product
     */
    function editProduct(productId, formData) {
        showLoading();
        
        $.ajax({
            url: eseabasi_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=eseabasi_edit_product&product_id=' + productId + '&nonce=' + eseabasi_ajax.nonce,
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showNotice('success', response.data.message);
                    closeModal();
                    location.reload(); // Refresh to show updated product
                } else {
                    showNotice('error', response.data.message || eseabasi_ajax.strings.error);
                }
            },
            error: function() {
                hideLoading();
                showNotice('error', eseabasi_ajax.strings.error);
            }
        });
    }
    
    /**
     * Delete product
     */
    function deleteProduct(productId) {
        showLoading();
        
        $.ajax({
            url: eseabasi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'eseabasi_delete_product',
                product_id: productId,
                nonce: eseabasi_ajax.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showNotice('success', response.data.message);
                    location.reload(); // Refresh to remove deleted product
                } else {
                    showNotice('error', response.data.message || eseabasi_ajax.strings.error);
                }
            },
            error: function() {
                hideLoading();
                showNotice('error', eseabasi_ajax.strings.error);
            }
        });
    }
    
    /**
     * Clear history
     */
    function clearHistory(type) {
        showLoading();
        
        $.ajax({
            url: eseabasi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'eseabasi_clear_history',
                type: type,
                nonce: eseabasi_ajax.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showNotice('success', response.data.message);
                    location.reload(); // Refresh to show cleared history
                } else {
                    showNotice('error', response.data.message || eseabasi_ajax.strings.error);
                }
            },
            error: function() {
                hideLoading();
                showNotice('error', eseabasi_ajax.strings.error);
            }
        });
    }
    
    /**
     * Delete history item
     */
    function deleteHistoryItem(itemId, type) {
        showLoading();
        
        $.ajax({
            url: eseabasi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'eseabasi_delete_history_item',
                item_id: itemId,
                type: type,
                nonce: eseabasi_ajax.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showNotice('success', response.data.message);
                    location.reload(); // Refresh to remove deleted item
                } else {
                    showNotice('error', response.data.message || eseabasi_ajax.strings.error);
                }
            },
            error: function() {
                hideLoading();
                showNotice('error', eseabasi_ajax.strings.error);
            }
        });
    }
    
    /**
     * Show loading indicator
     */
    function showLoading() {
        if ($('.eseabasi-loading').length === 0) {
            $('body').append('<div class="eseabasi-loading-overlay"><div class="eseabasi-loading"></div></div>');
        }
    }
    
    /**
     * Hide loading indicator
     */
    function hideLoading() {
        $('.eseabasi-loading-overlay').remove();
    }
    
    /**
     * Show notice
     */
    function showNotice(type, message) {
        var notice = $('<div class="eseabasi-notice ' + type + '">' + message + '</div>');
        $('.eseabasi-admin h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);
    }
    
    /**
     * Close modal
     */
    function closeModal() {
        $('#eseabasi-product-modal').fadeOut(function() {
            $(this).remove();
        });
    }
    
    /**
     * Modal event handlers
     */
    $(document).on('click', '.eseabasi-modal-close', function() {
        closeModal();
    });
    
    $(document).on('click', '.eseabasi-modal', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
})(jQuery);

// Modal CSS (will be added dynamically)
jQuery(document).ready(function($) {
    if ($('#eseabasi-modal-styles').length === 0) {
        var modalStyles = `
            <style id="eseabasi-modal-styles">
                .eseabasi-modal {
                    display: none;
                    position: fixed;
                    z-index: 100000;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0,0,0,0.5);
                }
                
                .eseabasi-modal-content {
                    background-color: #fff;
                    margin: 5% auto;
                    padding: 0;
                    border-radius: 8px;
                    width: 90%;
                    max-width: 600px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                }
                
                .eseabasi-modal-header {
                    background: #FF0000;
                    color: white;
                    padding: 20px;
                    border-radius: 8px 8px 0 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .eseabasi-modal-header h3 {
                    margin: 0;
                    font-size: 1.3em;
                }
                
                .eseabasi-modal-close {
                    font-size: 28px;
                    font-weight: bold;
                    cursor: pointer;
                    line-height: 1;
                }
                
                .eseabasi-modal-close:hover {
                    opacity: 0.7;
                }
                
                .eseabasi-modal-body {
                    padding: 20px;
                }
                
                .eseabasi-modal-footer {
                    padding: 15px 20px;
                    border-top: 1px solid #ddd;
                    text-align: right;
                }
                
                .eseabasi-modal-footer .button {
                    margin-left: 10px;
                }
                
                .eseabasi-loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 100001;
                }
                
                .eseabasi-loading {
                    width: 50px;
                    height: 50px;
                    border: 5px solid #f3f3f3;
                    border-top: 5px solid #FF0000;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }
            </style>
        `;
        
        $('head').append(modalStyles);
    }
});