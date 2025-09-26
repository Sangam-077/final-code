document.addEventListener('DOMContentLoaded', function() {
    // Initialize the POS system
    initPOS();
    
    // Update date and time
    updateDateTime();
    setInterval(updateDateTime, 60000);
});

function initPOS() {
    // Initialize cart from session if available
    updateCartDisplay();
    
    // Set up event listeners
    setupEventListeners();
}

function updateDateTime() {
    const now = new Date();
    document.getElementById('current-date').textContent = now.toLocaleDateString();
    document.getElementById('current-time').textContent = now.toLocaleTimeString();
}

function setupEventListeners() {
    // Category tabs
    document.querySelectorAll('.category-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            const category = this.getAttribute('data-category');
            filterProducts(category);
        });
    });
    
    // Add to cart buttons
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const productCard = this.closest('.product-card');
            const productId = productCard.getAttribute('data-id');
            const productName = productCard.getAttribute('data-name');
            const productPrice = parseFloat(productCard.getAttribute('data-price'));
            const stock = parseInt(productCard.getAttribute('data-stock'));
            const allergens = productCard.getAttribute('data-allergens') || '';
            
            if (stock <= 0) {
                alert('This product is out of stock');
                return;
            }
            
            openAllergyModal(productId, productName, productPrice, allergens);
        });
    });
    
    // Clear order button
    const clearOrderBtn = document.getElementById('clear-order');
    if (clearOrderBtn) {
        clearOrderBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to clear the current order?')) {
                clearCart();
            }
        });
    }
    
    // Checkout button
    const checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function() {
            if (getCartTotalQuantity() === 0) {
                alert('Please add items to the order before checkout');
                return;
            }
            openPaymentModal();
        });
    }
    
    // Payment method change
    document.querySelectorAll('input[name="payment-method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updatePaymentMethodDisplay(this.value);
        });
    });
    
    // Cash received input
    const cashReceived = document.getElementById('cash-received');
    if (cashReceived) {
        cashReceived.addEventListener('input', calculateChange);
    }
    
    // Modal controls
    setupModalControls();
}

function filterProducts(category) {
    const productCategories = document.querySelectorAll('.product-category');
    
    if (category === 'All') {
        productCategories.forEach(cat => cat.style.display = 'block');
    } else {
        productCategories.forEach(cat => {
            if (cat.getAttribute('data-category') === category) {
                cat.style.display = 'block';
            } else {
                cat.style.display = 'none';
            }
        });
    }
}

function addToCart(id, name, price, quantity, customisations = '') {
    let cart = JSON.parse(sessionStorage.getItem('pos_cart')) || [];
    const itemKey = `${id}_${customisations || 'default'}`;
    const existingItemIndex = cart.findIndex(item => `${item.id}_${item.customisations || 'default'}` === itemKey);
    
    if (existingItemIndex !== -1) {
        cart[existingItemIndex].quantity += quantity;
    } else {
        cart.push({ id, name, price, quantity, customisations });
    }
    
    sessionStorage.setItem('pos_cart', JSON.stringify(cart));
    syncCartToServer();
    updateCartDisplay();
}

function removeFromCart(id, customisations = '') {
    let cart = JSON.parse(sessionStorage.getItem('pos_cart')) || [];
    const itemKey = `${id}_${customisations || 'default'}`;
    cart = cart.filter(item => `${item.id}_${item.customisations || 'default'}` !== itemKey);
    sessionStorage.setItem('pos_cart', JSON.stringify(cart));
    syncCartToServer();
    updateCartDisplay();
}

function updateCartItemQuantity(id, change, customisations = '') {
    let cart = JSON.parse(sessionStorage.getItem('pos_cart')) || [];
    const itemKey = `${id}_${customisations || 'default'}`;
    const itemIndex = cart.findIndex(item => `${item.id}_${item.customisations || 'default'}` === itemKey);
    
    if (itemIndex !== -1) {
        cart[itemIndex].quantity += change;
        if (cart[itemIndex].quantity <= 0) {
            cart.splice(itemIndex, 1);
        }
        sessionStorage.setItem('pos_cart', JSON.stringify(cart));
        syncCartToServer();
        updateCartDisplay();
    }
}

function clearCart() {
    sessionStorage.removeItem('pos_cart');
    syncCartToServer();
    updateCartDisplay();
}

function getCartTotalQuantity() {
    const cart = JSON.parse(sessionStorage.getItem('pos_cart')) || [];
    return cart.reduce((total, item) => total + item.quantity, 0);
}

function getCartSubtotal() {
    const cart = JSON.parse(sessionStorage.getItem('pos_cart')) || [];
    return cart.reduce((total, item) => total + (item.price * item.quantity), 0);
}

function updateCartDisplay() {
    const cart = JSON.parse(sessionStorage.getItem('pos_cart')) || [];
    const cartBody = document.getElementById('order-items-body');
    const emptyCartMessage = document.getElementById('empty-cart-message');
    const subtotalElement = document.getElementById('subtotal');
    const taxElement = document.getElementById('tax');
    const totalElement = document.getElementById('total');
    
    if (!cartBody || !emptyCartMessage || !subtotalElement || !taxElement || !totalElement) {
        console.error('Cart display elements missing');
        return;
    }
    
    if (cart.length === 0) {
        cartBody.innerHTML = '';
        emptyCartMessage.style.display = 'block';
    } else {
        emptyCartMessage.style.display = 'none';
        let cartHTML = '';
        cart.forEach(item => {
            const itemTotal = (item.price * item.quantity).toFixed(2);
            const escapedCustom = item.customisations ? item.customisations.replace(/'/g, "\\'").replace(/"/g, '\\"') : '';
            cartHTML += `
                <tr>
                    <td>${item.name}${item.customisations ? ` <small>(Custom: ${item.customisations})</small>` : ''}</td>
                    <td>
                        <div class="quantity-controls">
                            <button class="quantity-btn" onclick="updateCartItemQuantity('${item.id}', -1, '${escapedCustom}')">-</button>
                            <span>${item.quantity}</span>
                            <button class="quantity-btn" onclick="updateCartItemQuantity('${item.id}', 1, '${escapedCustom}')">+</button>
                        </div>
                    </td>
                    <td>$${item.price.toFixed(2)}</td>
                    <td>$${itemTotal}</td>
                    <td>
                        <button class="remove-item" onclick="removeFromCart('${item.id}', '${escapedCustom}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        cartBody.innerHTML = cartHTML;
    }
    
    const subtotal = getCartSubtotal();
    const tax = subtotal * 0.1;
    const total = subtotal + tax;
    
    subtotalElement.textContent = `$${subtotal.toFixed(2)}`;
    taxElement.textContent = `$${tax.toFixed(2)}`;
    totalElement.textContent = `$${total.toFixed(2)}`;
}

function openPaymentModal() {
    const modal = document.getElementById('payment-modal');
    const paymentTotal = document.getElementById('payment-total');
    const paymentMethod = document.querySelector('input[name="payment-method"]:checked');
    const paymentMethodDisplay = document.getElementById('payment-method');
    
    if (!modal || !paymentTotal || !paymentMethod || !paymentMethodDisplay) {
        console.error('Payment modal elements missing');
        alert('Error: Payment modal setup failed');
        return;
    }
    
    const subtotal = getCartSubtotal();
    const tax = subtotal * 0.1;
    const total = subtotal + tax;
    
    paymentTotal.textContent = `$${total.toFixed(2)}`;
    paymentMethodDisplay.textContent = paymentMethod.value.charAt(0).toUpperCase() + paymentMethod.value.slice(1);
    updatePaymentMethodDisplay(paymentMethod.value);
    
    document.getElementById('cash-received').value = '';
    document.getElementById('change-amount').textContent = '$0.00';
    
    modal.style.display = 'block';
}

function updatePaymentMethodDisplay(method) {
    const paymentSections = ['cash-payment', 'card-payment', 'mobile-payment'];
    paymentSections.forEach(section => {
        const element = document.getElementById(section);
        if (element) {
            element.style.display = section === `${method}-payment` ? 'block' : 'none';
        }
    });
}

function calculateChange() {
    const cashReceivedInput = document.getElementById('cash-received');
    const changeAmount = document.getElementById('change-amount');
    if (!cashReceivedInput || !changeAmount) {
        console.error('Cash input or change amount element missing');
        return;
    }
    const cashReceived = parseFloat(cashReceivedInput.value) || 0;
    const subtotal = getCartSubtotal();
    const tax = subtotal * 0.1;
    const total = subtotal + tax;
    const change = cashReceived - total;
    changeAmount.textContent = `$${change >= 0 ? change.toFixed(2) : '0.00'}`;
}

function syncCartToServer() {
    const cart = JSON.parse(sessionStorage.getItem('pos_cart')) || [];
    console.log('Syncing cart to server:', cart);
    fetch('process_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'sync_cart', cart })
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.error) {
            console.error('Cart sync error:', data.error);
            alert('Failed to sync cart: ' + data.error);
        } else {
            console.log('Cart synced successfully:', data);
        }
    })
    .catch(err => {
        console.error('Cart sync failed:', err);
        alert('Network error syncing cart: ' + err.message);
    });
}

function getCashierId() {
    return fetch('process_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_cashier_id' })
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.error || !data.cashier_id) {
            console.error('Cashier ID fetch error:', data.error);
            throw new Error('No valid cashier ID');
        }
        console.log('Fetched cashier_id:', data.cashier_id);
        return data.cashier_id;
    })
    .catch(err => {
        console.error('Failed to fetch cashier_id:', err);
        throw err;
    });
}

function processPayment() {
    const paymentMethodInput = document.querySelector('input[name="payment-method"]:checked');
    if (!paymentMethodInput) {
        console.error('No payment method selected');
        alert('Please select a payment method');
        return;
    }
    const paymentMethod = paymentMethodInput.value;
    const subtotal = getCartSubtotal();
    const tax = subtotal * 0.1;
    const total = subtotal + tax;
    
    if (paymentMethod === 'cash') {
        const cashReceivedInput = document.getElementById('cash-received');
        if (!cashReceivedInput) {
            console.error('Cash received input missing');
            alert('Error: Cash input not found');
            return;
        }
        const cashReceived = parseFloat(cashReceivedInput.value) || 0;
        if (cashReceived < total) {
            console.error('Insufficient cash received:', cashReceived, 'Total:', total);
            alert('Insufficient cash received');
            return;
        }
    }
    
    getCashierId().then(cashier_id => {
        const orderData = {
            action: 'process_order',
            items: JSON.parse(sessionStorage.getItem('pos_cart')) || [],
            subtotal,
            tax,
            total,
            payment_method: paymentMethod,
            cashier_id: cashier_id,
            timestamp: new Date().toISOString()
        };
        
        if (orderData.items.length === 0) {
            console.error('Empty cart in processPayment');
            alert('Cart is empty');
            return;
        }
        
        console.log('Submitting order:', orderData);
        const confirmBtn = document.getElementById('confirm-payment');
        if (!confirmBtn) {
            console.error('Confirm payment button missing');
            alert('Error: Confirm button not found');
            return;
        }
        
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        fetch('process_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData)
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
            return response.json();
        })
        .then(data => {
            console.log('Server response:', data);
            if (data.success) {
                console.log('Order processed:', data);
                alert('Order processed successfully! Order ID: ' + data.order_id);
                clearCart();
                document.getElementById('payment-modal').style.display = 'none';
                window.location.reload(); // Refresh to update stock
            } else {
                console.error('Order failed:', data.error);
                alert('Order processing failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            alert('Network error processing order: ' + err.message);
        })
        .finally(() => {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Payment';
        });
    }).catch(err => {
        console.error('Cashier ID error:', err);
        alert('Error: Please log in again');
        window.location.href = 'login.php';
    });
}

function openAllergyModal(id, name, price, allergens) {
    console.log('Opening modal for:', { id, name, price, allergens });
    const modal = document.getElementById('allergy-modal');
    if (!modal) {
        console.error('Modal not found');
        return;
    }
    
    document.getElementById('modal-item-name').textContent = `Customize ${name}`;
    document.getElementById('modal-inherent').textContent = `Contains: ${allergens || 'None'}`;
    
    // Reset selections
    document.querySelectorAll('#allergy-modal input[type="checkbox"]').forEach(cb => cb.checked = false);
    document.getElementById('substitution-select').value = '';
    document.getElementById('special-notes').value = '';
    document.getElementById('item-qty').value = 1;
    
    // Store current item data globally for confirm
    window.currentItem = { id, name, price, allergens };
    
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function confirmCustomization() {
    const modal = document.getElementById('allergy-modal');
    const currentItem = window.currentItem;
    if (!currentItem) return;
    
    // Build customisations string
    const avoided = Array.from(document.querySelectorAll('#allergy-modal input[type="checkbox"]:checked')).map(cb => cb.value).join(',');
    const substitution = document.getElementById('substitution-select').value;
    const notes = document.getElementById('special-notes').value.trim();
    const qty = parseInt(document.getElementById('item-qty').value) || 1;
    
    let customisations = '';
    if (avoided) customisations += `Avoid: ${avoided}; `;
    if (substitution) customisations += `Sub: ${substitution}; `;
    if (notes) customisations += `Notes: ${notes}`;
    customisations = customisations.trim() || null;
    
    // Add to cart with customizations
    addToCart(currentItem.id, currentItem.name, currentItem.price, qty, customisations);
    
    // Close modal
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    
    console.log('Added to cart with customizations:', customisations);
}

function setupModalControls() {
    const modal = document.getElementById('payment-modal');
    const closeBtn = document.querySelector('.close');
    const cancelBtn = document.getElementById('cancel-payment');
    const confirmBtn = document.getElementById('confirm-payment');
    
    if (!modal || !closeBtn || !cancelBtn || !confirmBtn) {
        console.error('Modal elements missing:', { modal, closeBtn, cancelBtn, confirmBtn });
        alert('Error: Payment modal setup failed');
        return;
    }
    
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    cancelBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    confirmBtn.addEventListener('click', function(event) {
        event.preventDefault();
        event.stopPropagation();
        console.log('Confirm payment clicked at:', new Date().toISOString());
        processPayment();
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Add listener for allergy modal confirm button
    document.getElementById('confirm-customize').addEventListener('click', confirmCustomization);

    // Handle quantity buttons in allergy modal
    document.querySelectorAll('#allergy-modal .qty-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const step = parseInt(this.getAttribute('data-step'));
            let qtyInput = document.getElementById('item-qty');
            let qty = parseInt(qtyInput.value) || 1;
            qty = Math.max(1, qty + step);
            qtyInput.value = qty;
        });
    });
}

window.updateCartItemQuantity = updateCartItemQuantity;
window.removeFromCart = removeFromCart;