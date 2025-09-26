// script.js

// Mobile Menu Toggle
document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
    const mainNav = document.getElementById('main-nav');
    if (mainNav) mainNav.classList.toggle('active');
});

// Smooth Scroll for Nav Links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
            const mainNav = document.getElementById('main-nav');
            if (mainNav?.classList.contains('active')) {
                mainNav.classList.remove('active');
            }
        }
    });
});

// Newsletter Form Validation
const newsletterForm = document.getElementById('newsletter-form');
if (newsletterForm) {
    newsletterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const email = document.getElementById('newsletter-email')?.value.trim();
        console.log('Newsletter form submitted:', email);
        if (email && /^\S+@\S+\.\S+$/.test(email)) {
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=subscribe&email=${encodeURIComponent(email)}`
            })
            .then(res => {
                if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
                return res.json();
            })
            .then(data => {
                console.log('Newsletter response:', data);
                showNotification(data.status, data.message || (data.status === 'success' ? 'Thank you for subscribing!' : 'Failed to subscribe.'));
                if (data.status === 'success') this.reset();
            })
            .catch(err => {
                console.error('Newsletter submission error:', err);
                showNotification('error', 'Failed to subscribe: ' + err.message);
            });
        } else {
            showNotification('error', 'Please enter a valid email address.');
        }
    });
}

// Review Form Validation
const reviewForm = document.getElementById('review-form');
if (reviewForm) {
    reviewForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const reviewText = document.getElementById('review-text')?.value.trim();
        const rating = document.querySelector('input[name="rating"]:checked');
        console.log('Review form submitted:', { rating: rating?.value, reviewText });
        if (!rating) {
            showNotification('error', 'Please select a rating.');
            return;
        }
        if (!reviewText || reviewText.length < 10) {
            showNotification('error', 'Please write a review with at least 10 characters.');
            return;
        }
        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=submit_review&rating=${rating.value}&comment=${encodeURIComponent(reviewText)}`
        })
        .then(res => {
            if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
            return res.json();
        })
        .then(data => {
            console.log('Review response:', data);
            showNotification(data.status, data.message || (data.status === 'success' ? 'Thanks for your review!' : 'Failed to submit review.'));
            if (data.status === 'success') {
                this.reset();
                setTimeout(() => location.reload(), 2000); // Reload to show new review
            }
        })
        .catch(err => {
            console.error('Review submission error:', err);
            showNotification('error', 'Failed to submit review: ' + err.message);
        });
    });
}

// Notification System
function showNotification(type, message) {
    console.log('Notification:', { type, message });
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;
    document.body.appendChild(notification);
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
    notification.querySelector('.notification-close')?.addEventListener('click', () => {
        notification.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(() => notification.remove(), 300);
    });
}

// Swiper Initialization (for home page)
document.addEventListener('DOMContentLoaded', function() {
    const swiperContainer = document.querySelector('.mySwiper');
    if (swiperContainer && typeof Swiper !== 'undefined') {
        new Swiper('.mySwiper', {
            slidesPerView: 'auto',
            spaceBetween: 30,
            loop: true,
            centeredSlides: true,
            speed: 800,
            autoplay: { delay: 3000, disableOnInteraction: false },
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
            pagination: { el: '.swiper-pagination', clickable: true, dynamicBullets: true },
            breakpoints: {
                0: { slidesPerView: 1, spaceBetween: 20 },
                768: { slidesPerView: 2, spaceBetween: 30 },
                1024: { slidesPerView: 3, spaceBetween: 40 }
            }
        });
    }
});

// Timeline Animation on Scroll (for about page)
// Animates timeline items when they enter the viewport
document.addEventListener('DOMContentLoaded', () => {
    const timelineItems = document.querySelectorAll('.timeline-item');
    if (timelineItems.length > 0) {
        if (!('IntersectionObserver' in window)) {
            timelineItems.forEach(item => item.classList.add('visible'));
            return;
        }
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        timelineItems.forEach(item => observer.observe(item));
    }
});

// Auth Forms Validation (for login/register pages)
// Validates login and registration forms
document.addEventListener('DOMContentLoaded', () => {
    const signInForm = document.getElementById('signInForm');
    const signUpForm = document.getElementById('signUpForm');

    // Handle Facebook login button click
    const fbBtn = document.getElementById('fb-btn');
    if (fbBtn) {
        fbBtn.addEventListener('click', handleFacebookSignIn);
    }

    if (signInForm) {
        signInForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const email = document.getElementById('userEmail')?.value.trim();
            const password = document.getElementById('userPass')?.value.trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showNotification('error', 'Please enter a valid email address.');
                return;
            }
            if (!password || password.length < 6) {
                showNotification('error', 'Password must be at least 6 characters long.');
                return;
            }
            
            // Submit the form via AJAX with AJAX header
            const formData = new FormData(signInForm);
            fetch('login.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Ensure AJAX detection
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'error') {
                    // Show error notification
                    showNotification('error', data.message || 'An error occurred during login.');
                    // Update email field if sticky_email is provided
                    if (data.sticky_email) {
                        document.getElementById('userEmail').value = data.sticky_email;
                    }
                    // Optionally fetch and replace form section with HTML if needed
                    if (data.message.includes('error') && data.html) {
                        const formSection = document.querySelector('.form-section');
                        if (formSection) {
                            formSection.innerHTML = data.html;
                            attachAuthListeners(); // Re-attach event listeners
                        }
                    }
                } else if (data.status === 'success') {
                    // Successful login, redirect to the URL provided by the server
                    window.location.href = data.redirect || 'index.php';
                }
            })
            .catch(error => {
                console.error('Login error:', error);
                // Fallback: Fetch HTML response if JSON parsing fails (e.g., server error page)
                fetch('login.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.text())
                .then(html => {
                    const formMatch = html.match(/<div class="form-section">([\s\S]*?)<\/div>/);
                    if (formMatch) {
                        const formSection = document.querySelector('.form-section');
                        if (formSection) {
                            formSection.innerHTML = formMatch[0];
                            attachAuthListeners(); // Re-attach event listeners
                        }
                    }
                    showNotification('error', 'An error occurred. Please try again.');
                })
                .catch(err => {
                    console.error('Fallback error:', err);
                    showNotification('error', 'An error occurred. Please try again.');
                });
            });
        });
    }

    if (signUpForm) {
        signUpForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const firstName = document.getElementById('firstName')?.value.trim();
            const lastName = document.getElementById('lastName')?.value.trim();
            const email = document.getElementById('regEmail')?.value.trim();
            const password = document.getElementById('regPass')?.value.trim();
            const confirmPassword = document.getElementById('confirmPass')?.value.trim();
            const terms = document.querySelector('input[name="terms"]');
            
            // Validate form
            let isValid = true;
            let errorMessage = '';
            
            if (!firstName || firstName.length < 2 || !lastName || lastName.length < 2) {
                isValid = false;
                errorMessage = 'Names must be at least 2 characters long.';
            } else if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address.';
            } else if (!password || password.length < 6) {
                isValid = false;
                errorMessage = 'Password must be at least 6 characters long.';
            } else if (password !== confirmPassword) {
                isValid = false;
                errorMessage = 'Passwords do not match.';
                document.getElementById('regPass').focus();
                document.getElementById('regPass').value = '';
                document.getElementById('confirmPass').value = '';
            } else if (!terms?.checked) {
                isValid = false;
                errorMessage = 'Please agree to the Terms of Service and Privacy Policy.';
            }
            
            if (!isValid) {
                showNotification('error', errorMessage);
                return;
            }
            
            // Submit the form via AJAX
            const formData = new FormData(signUpForm);
            fetch('register.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('error')) {
                    const formSection = data.match(/<div class="form-section">([\s\S]*?)<\/div>/);
                    if (formSection) {
                        document.querySelector('.form-section').innerHTML = formSection[0];
                    }
                    attachAuthListeners(); // Re-attach event listeners
                } else {
                    // Successful registration, redirect
                    window.location.href = 'index.php';
                }
            })
            .catch(error => {
                console.error('Registration error:', error);
                showNotification('error', 'An error occurred during registration.');
            });
        });
    }
});

function handleGoogleSignIn(response) {
    // Get the redirect parameter from the current URL
    const urlParams = new URLSearchParams(window.location.search);
    const redirect = urlParams.get('redirect') || 'index.php';
    console.log('Google Sign-In: Sending redirect=', redirect);

    fetch('social_login.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            provider: 'google',
            credential: response.credential,
            redirect: redirect
        })
    })
    .then(res => res.json())
    .then(data => {
        console.log('Google Sign-In Response:', data);
        if (data.status === 'success') {
            const redirectUrl = data.redirect || 'index.php';
            console.log('Google Sign-In: Redirecting to', redirectUrl);
            window.location.href = redirectUrl;
        } else {
            showNotification('error', data.message || 'Google sign-in failed.');
        }
    })
    .catch(error => {
        console.error('Google sign-in error:', error);
        showNotification('error', 'Google sign-in failed.');
    });
}

function handleFacebookSignIn() {
    if (typeof FB !== 'undefined') {
        // Get the redirect parameter from the current URL
        const urlParams = new URLSearchParams(window.location.search);
        const redirect = urlParams.get('redirect') || 'index.php';
        console.log('Facebook Sign-In: Sending redirect=', redirect);

        FB.login(function(response) {
            if (response.authResponse) {
                const accessToken = response.authResponse.accessToken;
                fetch('social_login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        provider: 'facebook',
                        access_token: accessToken,
                        redirect: redirect
                    })
                })
                .then(res => res.json())
                .then(data => {
                    console.log('Facebook Sign-In Response:', data);
                    if (data.status === 'success') {
                        const redirectUrl = data.redirect || 'index.php';
                        console.log('Facebook Sign-In: Redirecting to', redirectUrl);
                        window.location.href = redirectUrl;
                    } else {
                        showNotification('error', data.message || 'Facebook sign-in failed.');
                    }
                })
                .catch(error => {
                    console.error('Facebook sign-in error:', error);
                    showNotification('error', 'Facebook sign-in failed.');
                });
            } else {
                showNotification('error', 'Facebook sign-in was cancelled.');
            }
        }, {scope: 'email,public_profile'});
    } else {
        showNotification('error', 'Facebook SDK not loaded.');
    }
}

// Re-attach event listeners after form replacement
function attachAuthListeners() {
    const signInForm = document.getElementById('signInForm');
    const signUpForm = document.getElementById('signUpForm');
    
    if (signInForm) {
        signInForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const email = document.getElementById('userEmail')?.value.trim();
            const password = document.getElementById('userPass')?.value.trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showNotification('error', 'Please enter a valid email address.');
                return;
            }
            if (!password || password.length < 6) {
                showNotification('error', 'Password must be at least 6 characters long.');
                return;
            }
            const formData = new FormData(signInForm);
            fetch('login.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Ensure AJAX detection
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'error') {
                    showNotification('error', data.message || 'An error occurred during login.');
                    if (data.sticky_email) {
                        document.getElementById('userEmail').value = data.sticky_email;
                    }
                    if (data.html) {
                        const formSection = document.querySelector('.form-section');
                        if (formSection) {
                            formSection.innerHTML = data.html;
                            attachAuthListeners(); // Re-attach event listeners
                        }
                    }
                } else if (data.status === 'success') {
                    window.location.href = data.redirect || 'index.php';
                }
            })
            .catch(error => {
                console.error('Login error:', error);
                fetch('login.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.text())
                .then(html => {
                    const formMatch = html.match(/<div class="form-section">([\s\S]*?)<\/div>/);
                    if (formMatch) {
                        const formSection = document.querySelector('.form-section');
                        if (formSection) {
                            formSection.innerHTML = formMatch[0];
                            attachAuthListeners(); // Re-attach event listeners
                        }
                    }
                    showNotification('error', 'An error occurred. Please try again.');
                })
                .catch(err => {
                    console.error('Fallback error:', err);
                    showNotification('error', 'An error occurred. Please try again.');
                });
            });
        });
    }
    
    if (signUpForm) {
        signUpForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const firstName = document.getElementById('firstName')?.value.trim();
            const lastName = document.getElementById('lastName')?.value.trim();
            const email = document.getElementById('regEmail')?.value.trim();
            const password = document.getElementById('regPass')?.value.trim();
            const confirmPassword = document.getElementById('confirmPass')?.value.trim();
            const terms = document.querySelector('input[name="terms"]');
            
            let isValid = true;
            let errorMessage = '';
            
            if (!firstName || firstName.length < 2 || !lastName || lastName.length < 2) {
                isValid = false;
                errorMessage = 'Names must be at least 2 characters long.';
            } else if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address.';
            } else if (!password || password.length < 6) {
                isValid = false;
                errorMessage = 'Password must be at least 6 characters long.';
            } else if (password !== confirmPassword) {
                isValid = false;
                errorMessage = 'Passwords do not match.';
                document.getElementById('regPass').focus();
                document.getElementById('regPass').value = '';
                document.getElementById('confirmPass').value = '';
            } else if (!terms?.checked) {
                isValid = false;
                errorMessage = 'Please agree to the Terms of Service and Privacy Policy.';
            }
            
            if (!isValid) {
                showNotification('error', errorMessage);
                return;
            }
            
            const formData = new FormData(signUpForm);
            fetch('register.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('error')) {
                    const formSection = data.match(/<div class="form-section">([\s\S]*?)<\/div>/);
                    if (formSection) {
                        document.querySelector('.form-section').innerHTML = formSection[0];
                    }
                    attachAuthListeners(); // Re-attach event listeners
                } else {
                    window.location.href = 'index.php';
                }
            })
            .catch(error => {
                console.error('Registration error:', error);
                showNotification('error', 'An error occurred during registration.');
            });
        });
    }
    
    // Re-attach FB button
    const fbBtn = document.getElementById('fb-btn');
    if (fbBtn) {
        fbBtn.addEventListener('click', handleFacebookSignIn);
    }
}
// Utility Function to Update Badge Counts
// Updates or removes badge elements based on count
function updateBadge(elementId, count) {
    let badge = document.querySelector(`#${elementId} .count-badge`);
    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'count-badge';
            document.querySelector(`#${elementId}`).appendChild(badge);
        }
        badge.textContent = count;
    } else if (badge) {
        badge.remove();
    }
}

// Placeholder for showNotification (implement this function)
function showNotification(type, message) {
    // Example implementation (replace with your notification system)
    console.log(`[${type}] ${message}`);
    // You might want to use a library like Toastify or a custom alert
}

// Notification System
// Displays temporary notifications with specified type and message
function showNotification(type, message) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;
    document.body.appendChild(notification);
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
    notification.querySelector('.notification-close')?.addEventListener('click', () => {
        notification.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(() => notification.remove(), 300);
    });
}

// Utility Function to Update Badge Counts
// Updates or removes badge elements based on count
function updateBadge(elementId, count) {
    let badge = document.querySelector(`#${elementId} .count-badge`);
    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'count-badge';
            document.querySelector(`#${elementId}`).appendChild(badge);
        }
        badge.textContent = count;
    } else if (badge) {
        badge.remove();
    }
}

// Menu Page Specific Functionality
// Handles menu filtering and scroll spy behavior
document.addEventListener('DOMContentLoaded', function() {
    if (!document.querySelector('.menu-page')) return;

    const filterButtons = document.querySelectorAll('.filter-btn');
    const categorySections = document.querySelectorAll('.category-section');
    let isAllSelected = true;
    let observer = null;

    function getHeaderOffset() {
        const header = document.querySelector('header');
        return header ? header.offsetHeight + 12 : 0;
    }

    function smoothScrollToEl(el) {
        const y = el.getBoundingClientRect().top + window.pageYOffset - getHeaderOffset();
        window.scrollTo({ top: y, behavior: 'smooth' });
    }

    const headerOffset = getHeaderOffset();
    categorySections.forEach(sec => (sec.style.scrollMarginTop = headerOffset + 'px'));

    function filterByCategory(targetCategory) {
        isAllSelected = (targetCategory === 'All');
        let visibleCount = 0;

        categorySections.forEach(section => {
            const sectionCategory = section.getAttribute('data-category');
            if (targetCategory === 'All' || sectionCategory === targetCategory) {
                section.style.display = 'block';
                section.style.opacity = '0';
                setTimeout(() => {
                    section.style.transition = 'opacity 0.5s ease';
                    section.style.opacity = '1';
                }, 50);
                visibleCount++;
            } else {
                section.style.display = 'none';
                section.style.opacity = '0';
            }
        });

        if (isAllSelected) {
            initScrollSpy();
        } else if (observer) {
            categorySections.forEach(section => observer.unobserve(section));
        }
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filterByCategory(btn.getAttribute('data-category'));
            if (btn.getAttribute('data-category') !== 'All') {
                const section = document.querySelector(`.category-section[data-category="${btn.getAttribute('data-category')}"]`);
                if (section) smoothScrollToEl(section);
            }
        });
    });

    function initScrollSpy() {
        if ('IntersectionObserver' in window) {
            observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && isAllSelected) {
                        const category = entry.target.getAttribute('data-category');
                        filterButtons.forEach(btn => {
                            btn.classList.toggle('active', btn.getAttribute('data-category') === category);
                        });
                        const allBtn = document.querySelector('.filter-btn[data-category="All"]');
                        if (allBtn) allBtn.classList.remove('active');
                    }
                });
            }, { threshold: 0.3, rootMargin: `-${headerOffset}px 0px` });
            categorySections.forEach(section => observer.observe(section));
        }
    }

    // Initialize with all categories visible
    filterByCategory('All');
    initScrollSpy();
});

// Cart and Wishlist Functionality
// Manages cart and wishlist interactions
document.addEventListener('DOMContentLoaded', function() {
    // Load Cart
    // Fetches and displays cart content
    async function loadCart() {
        const cartModal = document.getElementById('cart-modal');
        const cartModalContent = cartModal?.querySelector('.modal-content');
        if (!cartModalContent) return;

        cartModalContent.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';

        try {
            const response = await fetch('cart_page.php?action=get_cart_html');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            cartModalContent.innerHTML = await response.text();
            attachCartListeners();
        } catch (error) {
            console.error('Error loading cart:', error);
            cartModalContent.innerHTML = '<p>Error loading cart. <button onclick="loadCart()">Retry</button></p>';
            showNotification('error', 'Failed to load cart.');
        }
    }

    // Attach Cart Listeners
    // Adds event listeners for cart item actions
    function attachCartListeners() {
        document.querySelectorAll('.qty-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const container = btn.closest('.qty-control');
                const input = container?.querySelector('.qty-input');
                const index = btn.closest('.cart-item-modal')?.dataset.index;
                if (!input || !index) return;
                let qty = parseInt(input.value) || 1;
                qty += (btn.textContent === '+' ? 1 : -1);
                qty = Math.max(1, qty);
                input.value = qty;

                try {
                    const response = await fetch('cart_page.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=update&index=${index}&quantity=${qty}`
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        updateBadge('cart-btn', result.cartCount);
                        loadCart();
                    } else {
                        showNotification('error', result.message || 'Failed to update quantity.');
                        input.value = parseInt(input.value); // Revert if failed
                    }
                } catch (error) {
                    console.error('Error updating quantity:', error);
                    showNotification('error', 'Failed to update quantity.');
                }
            });
        });

        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const index = btn.closest('.cart-item-modal')?.dataset.index;
                if (!index) return;

                try {
                    const response = await fetch('cart_page.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=remove&index=${index}`
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        updateBadge('cart-btn', result.cartCount);
                        loadCart();
                    } else {
                        showNotification('error', result.message || 'Failed to remove item.');
                    }
                } catch (error) {
                    console.error('Error removing item:', error);
                    showNotification('error', 'Failed to remove item.');
                }
            });
        });

        const promoBtn = document.querySelector('.apply-promo-btn');
        const promoInput = document.querySelector('.promo-input');
        if (promoBtn && promoInput) {
            promoBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                const code = promoInput.value.trim();
                if (!code) {
                    showNotification('error', 'Please enter a promo code.');
                    return;
                }
                try {
                    const response = await fetch('cart_page.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=apply_promo&code=${encodeURIComponent(code)}`
                    });
                    const result = await response.json();
                    showNotification(result.status === 'success' ? 'success' : 'error', result.message);
                    if (result.status === 'success') loadCart();
                } catch (error) {
                    console.error('Error applying promo:', error);
                    showNotification('error', 'Failed to apply promo code.');
                }
            });
        }

        const shippingSelect = document.querySelector('.shipping-select');
        if (shippingSelect) {
            shippingSelect.addEventListener('change', async (e) => {
                try {
                    const response = await fetch('cart_page.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=update_shipping&value=${shippingSelect.value}`
                    });
                    const result = await response.json();
                    if (result.status === 'success') loadCart();
                    else showNotification('error', 'Failed to update shipping.');
                } catch (error) {
                    console.error('Error updating shipping:', error);
                    showNotification('error', 'Failed to update shipping.');
                }
            });
        }
    }

    // Load Wishlist
    // Fetches and displays wishlist content
    async function loadWishlist() {
        const wishlistModal = document.getElementById('wishlist-modal');
        const wishlistContent = wishlistModal?.querySelector('.modal-content');
        if (!wishlistContent) return;

        wishlistContent.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';

        try {
            const response = await fetch('cart_page.php?action=get_wishlist_html');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            wishlistContent.innerHTML = await response.text();
            attachWishlistListeners();
        } catch (error) {
            console.error('Error loading wishlist:', error);
            wishlistContent.innerHTML = '<p>Error loading wishlist. <button onclick="loadWishlist()">Retry</button></p>';
            showNotification('error', 'Failed to load wishlist.');
        }
    }

    // Attach Wishlist Listeners
    // Adds event listeners for wishlist item actions
    function attachWishlistListeners() {
        document.querySelectorAll('.wishlist-remove-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const index = btn.closest('.wishlist-item')?.dataset.index;
                if (!index) return;
                try {
                    const response = await fetch('cart_page.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=remove_wish&index=${index}`
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        loadWishlist();
                        updateBadge('wishlist-btn', result.wishlistCount);
                    } else {
                        showNotification('error', result.message || 'Failed to remove item.');
                    }
                } catch (error) {
                    console.error('Error removing wishlist item:', error);
                    showNotification('error', 'Failed to remove item.');
                }
            });
        });
    }

    // Allergy Modal and Add to Cart
    // Manages allergy modal and cart addition process
    const addButtons = document.querySelectorAll('.add-btn');
    const allergyModal = document.getElementById('allergy-modal');
    const modalClose = document.querySelector('#allergy-modal .modal-close');
    const confirmAdd = document.getElementById('confirm-add');
    const modalItemName = document.getElementById('modal-item-name');
    const modalInherent = document.getElementById('modal-inherent');
    const specialNotes = document.getElementById('special-notes');
    const itemQty = document.getElementById('item-qty');
    const qtyButtons = document.querySelectorAll('#allergy-modal .qty-btn');
    const chipInputs = document.querySelectorAll('#allergy-modal .chip input[type="checkbox"]');

    addButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            if (button.disabled) return;
            const product = {
                id: button.dataset.id,
                name: button.dataset.name,
                price: parseFloat(button.dataset.price || 0),
                allergens: button.dataset.allergy || 'None'
            };
            if (!product.id || !product.name) {
                console.error('Invalid product data:', button.dataset);
                showNotification('error', 'Invalid item selected.');
                return;
            }
            window.currentProduct = product;
            modalItemName.textContent = `Customize ${product.name}`;
            modalInherent.innerHTML = product.allergens !== 'None' ? `<i class="fas fa-exclamation-triangle"></i> Contains: ${product.allergens}` : '';
            specialNotes.value = '';
            itemQty.value = 1;
            chipInputs.forEach(input => input.checked = false);
            allergyModal.classList.add('show');
            allergyModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            console.log('Opening allergy modal for product:', product);
        });
    });

    if (modalClose) {
        modalClose.addEventListener('click', () => {
            allergyModal.classList.remove('show');
            allergyModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            delete window.currentProduct;
        });
    }

    qtyButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            let qty = parseInt(itemQty.value) || 1;
            const step = parseInt(btn.dataset.step);
            qty = Math.max(1, qty + step);
            itemQty.value = qty;
        });
    });

    if (confirmAdd) {
        confirmAdd.addEventListener('click', async (e) => {
            e.preventDefault();
            const button = confirmAdd;
            if (button.disabled) return;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

            const currentProduct = window.currentProduct;
            if (!currentProduct) {
                showNotification('error', 'No item selected.');
                console.error('No current product set.');
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-check"></i> Add to Cart';
                return;
            }

            const allergensToAvoid = Array.from(chipInputs)
                .filter(input => input.checked)
                .map(input => input.value);
            const data = {
                action: 'add',
                product_id: currentProduct.id,
                quantity: parseInt(itemQty.value) || 1,
                allergens_to_avoid: allergensToAvoid,
                notes: specialNotes.value.trim()
            };
            console.log('Sending add to cart request with data:', data);

            try {
                const response = await fetch('cart_page.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                console.log('Received response from cart_page.php:', result);
                if (result.status === 'success') {
                    showNotification('success', result.message || 'Item added to cart!');
                    updateBadge('cart-btn', result.cartCount);
                    allergyModal.classList.remove('show');
                    allergyModal.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = '';
                    itemQty.value = 1;
                    specialNotes.value = '';
                    chipInputs.forEach(input => input.checked = false);
                    delete window.currentProduct;
                    if (document.getElementById('cart-modal')?.classList.contains('show')) {
                        loadCart();
                    }
                } else {
                    showNotification('error', result.message || 'Failed to add item to cart.');
                }
            } catch (error) {
                console.error('Error adding to cart:', error);
                showNotification('error', `Failed to add item to cart. Error: ${error.message}`);
            } finally {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-check"></i> Add to Cart';
            }
        });
    }

    // Cart Modal
    const cartIcon = document.getElementById('cart-btn');
    const cartModal = document.getElementById('cart-modal');
    const closeCartModal = cartModal?.querySelector('.close-modal');

    if (cartIcon && cartModal) {
        cartIcon.addEventListener('click', (e) => {
            e.preventDefault();
            loadCart();
            cartModal.classList.add('show');
            cartModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        });
    }

    if (cartIcon && cartModal) {
        cartIcon.addEventListener('click', (e) => {
            e.preventDefault();
            loadCart();
            cartModal.classList.add('show');
            cartModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        });
    }

    if (cartModal) {
        cartModal.addEventListener('click', (e) => {
            if (e.target === cartModal || e.target.closest('.close-modal')) {
                cartModal.classList.remove('show');
                cartModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
        });
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && cartModal?.classList.contains('show')) {
            cartModal.classList.remove('show');
            cartModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
    });

    // Wishlist Modal
    const wishlistIcon = document.getElementById('wishlist-btn');
    const wishlistModal = document.getElementById('wishlist-modal');
    const closeWishlistModal = wishlistModal?.querySelector('.close-modal');

    if (wishlistIcon && wishlistModal) {
        wishlistIcon.addEventListener('click', (e) => {
            e.preventDefault();
            loadWishlist();
            wishlistModal.classList.add('show');
            wishlistModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        });
    }

    if (closeWishlistModal) {
        closeWishlistModal.addEventListener('click', () => {
            wishlistModal.classList.remove('show');
            wishlistModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        });
    }

    if (wishlistModal) {
        wishlistModal.addEventListener('click', (e) => {
            if (e.target === wishlistModal) {
                wishlistModal.classList.remove('show');
                wishlistModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && wishlistModal?.classList.contains('show')) {
            wishlistModal.classList.remove('show');
            wishlistModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
    });

    // Wishlist Heart Buttons
    document.querySelectorAll('.wishlist-heart').forEach(heart => {
        heart.addEventListener('click', async (e) => {
            e.preventDefault();
            const button = heart;
            if (button.disabled) return;

            button.disabled = true;
            button.classList.add('loading');

            const id = button.dataset.id;
            const isActive = button.classList.contains('active');
            const action = isActive ? 'remove_wish' : 'add_wish';

            try {
                const response = await fetch('cart_page.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${action}&product_id=${id}`
                });
                const result = await response.json();
                if (result.status === 'success') {
                    button.classList.toggle('active');
                    button.querySelector('i').classList.toggle('far');
                    button.querySelector('i').classList.toggle('fas');
                    updateBadge('wishlist-btn', result.wishlistCount);
                    showNotification('success', result.message);
                    if (wishlistModal.classList.contains('show')) {
                        loadWishlist();
                    }
                } else {
                    showNotification('error', result.message);
                }
            } catch (error) {
                console.error('Error updating wishlist:', error);
                showNotification('error', 'Failed to update wishlist.');
            } finally {
                button.disabled = false;
                button.classList.remove('loading');
            }
        });
    });

    // Search Functionality
    // Handles search input and redirection
    document.querySelectorAll('.icon-link i.fa-search').forEach(icon => {
        icon.closest('a')?.addEventListener('click', (e) => {
            e.preventDefault();
            const searchTerm = prompt('Enter search term:');
            if (searchTerm) {
                window.location.href = `menu.php?search=${encodeURIComponent(searchTerm)}`;
            }
        });
    });
});
// Profile Dropdown Toggle (Works on Desktop and Mobile)
document.addEventListener('DOMContentLoaded', function() {
  const profileBtn = document.getElementById('profile-btn');
  const dropdownMenu = document.getElementById('account-menu');

  if (profileBtn && dropdownMenu) {
    profileBtn.addEventListener('click', function(e) {
      e.stopPropagation(); // Prevent closing immediately
      dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
    });
  }

  // Close dropdown when clicking outside
  document.addEventListener('click', function(e) {
    if (dropdownMenu && !dropdownMenu.contains(e.target) && !profileBtn.contains(e.target)) {
      dropdownMenu.style.display = 'none';
    }
  });

  // Close on ESC key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && dropdownMenu.style.display === 'block') {
      dropdownMenu.style.display = 'none';
    }
  });
  
});
// Book Table functionality
// Book Table functionality
document.addEventListener('DOMContentLoaded', function() {
  // Add functionality for popular time slot buttons
  const timeSlotButtons = document.querySelectorAll('.slot-btn');
  const timeSelect = document.getElementById('time');
  
  timeSlotButtons.forEach(button => {
    button.addEventListener('click', function() {
      const timeValue = this.getAttribute('data-time');
      timeSelect.value = timeValue;
      
      // Visual feedback
      timeSlotButtons.forEach(btn => btn.classList.remove('active'));
      this.classList.add('active');
    });
  });
  
  // Form handling with AJAX
  const reservationForm = document.getElementById('reservation-form');
  if (reservationForm) {
    reservationForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      let isValid = true;
      const inputs = this.querySelectorAll('input[required], select[required]');
      
      inputs.forEach(input => {
        if (!input.value.trim()) {
          isValid = false;
          highlightError(input);
        } else {
          removeErrorHighlight(input);
        }
      });
      
      // Validate email format
      const emailInput = document.getElementById('email');
      if (emailInput.value && !isValidEmail(emailInput.value)) {
        isValid = false;
        highlightError(emailInput);
        showFieldError(emailInput, 'Please enter a valid email address');
      } else {
        removeErrorHighlight(emailInput);
        clearFieldError(emailInput);
      }
      
      // Validate phone format
      const phoneInput = document.getElementById('phone');
      if (phoneInput.value && !isValidPhone(phoneInput.value)) {
        isValid = false;
        highlightError(phoneInput);
        showFieldError(phoneInput, 'Please enter a valid phone number');
      } else {
        removeErrorHighlight(phoneInput);
        clearFieldError(phoneInput);
      }
      
      // Validate date is in the future
      const dateInput = document.getElementById('date');
      if (dateInput.value && !isFutureDate(dateInput.value)) {
        isValid = false;
        highlightError(dateInput);
        showFieldError(dateInput, 'Please select a future date');
      } else {
        removeErrorHighlight(dateInput);
        clearFieldError(dateInput);
      }
      
      if (!isValid) {
        // Scroll to first error
        const firstError = this.querySelector('.error-field');
        if (firstError) {
          firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
      }
      
      // Submit via AJAX
      const formData = new FormData(this);
      fetch('/book_table.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(res => {
        if (!res.ok) {
          throw new Error(`HTTP error! Status: ${res.status}`);
        }
        return res.text(); // Get raw response first
      })
      .then(text => {
        console.log('Raw response:', text); // Debug the raw response
        try {
          const data = JSON.parse(text); // Attempt to parse as JSON
          console.log('Parsed data:', data); // Debug the parsed data
          if (data.status && data.message) {
            showNotification(data.status, data.message);
            if (data.status === 'success') {
              this.reset(); // Clear form
              timeSelect.value = ''; // Reset time select
              timeSlotButtons.forEach(btn => btn.classList.remove('active')); // Reset slot buttons
            }
          } else {
            throw new Error('Invalid response format');
          }
        } catch (e) {
          console.error('JSON parsing error:', e, 'Response:', text);
          showNotification('error', 'Failed to process response. Please try again.');
        }
      })
      .catch(err => {
        console.error('Fetch error:', err);
        showNotification('error', 'Failed to reserve the table. Please try again.');
      });
    });
  }
  
  // Real-time validation
  const emailInput = document.getElementById('email');
  if (emailInput) {
    emailInput.addEventListener('blur', function() {
      if (this.value && !isValidEmail(this.value)) {
        highlightError(this);
        showFieldError(this, 'Please enter a valid email address');
      } else {
        removeErrorHighlight(this);
        clearFieldError(this);
      }
    });
  }
  
  const phoneInput = document.getElementById('phone');
  if (phoneInput) {
    phoneInput.addEventListener('blur', function() {
      if (this.value && !isValidPhone(this.value)) {
        highlightError(this);
        showFieldError(this, 'Please enter a valid phone number');
      } else {
        removeErrorHighlight(this);
        clearFieldError(this);
      }
    });
  }
  
  const dateInput = document.getElementById('date');
  if (dateInput) {
    dateInput.addEventListener('change', function() {
      if (this.value && !isFutureDate(this.value)) {
        highlightError(this);
        showFieldError(this, 'Please select a future date');
      } else {
        removeErrorHighlight(this);
        clearFieldError(this);
      }
    });
  }
  
  // Auto-format phone number
  if (phoneInput) {
    phoneInput.addEventListener('input', function(e) {
      const input = e.target.value.replace(/\D/g, '').substring(0, 10);
      const trimmed = input.replace(/\D/g, '');
      
      const parts = [];
      if (trimmed.length > 0) parts.push(trimmed.substring(0, 3));
      if (trimmed.length > 3) parts.push(trimmed.substring(3, 6));
      if (trimmed.length > 6) parts.push(trimmed.substring(6, 10));
      
      e.target.value = parts.join('-');
    });
  }
  
  // Set minimum date to tomorrow
  if (dateInput) {
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    const minDate = tomorrow.toISOString().split('T')[0];
    dateInput.setAttribute('min', minDate);
  }
  
  // Helper functions
  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }
  
  function isValidPhone(phone) {
    const phoneRegex = /^[0-9+\s\(\)\-]{10,20}$/;
    return phoneRegex.test(phone);
  }
  
  function isFutureDate(dateString) {
    const selectedDate = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return selectedDate >= today;
  }
  
  function highlightError(element) {
    element.classList.add('error-field');
    element.style.borderColor = '#dc3545';
  }
  
  function removeErrorHighlight(element) {
    element.classList.remove('error-field');
    element.style.borderColor = '#ddd';
  }
  
  function showFieldError(element, message) {
    clearFieldError(element);
    const errorElement = document.createElement('div');
    errorElement.className = 'field-error';
    errorElement.style.color = '#dc3545';
    errorElement.style.fontSize = '14px';
    errorElement.style.marginTop = '5px';
    errorElement.textContent = message;
    element.parentNode.appendChild(errorElement);
  }
  
  function clearFieldError(element) {
    const existingError = element.parentNode.querySelector('.field-error');
    if (existingError) existingError.remove();
  }
  
  // Notification System
  function showNotification(type, message) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `<span>${message}</span><button class="notification-close">&times;</button>`;
    document.body.appendChild(notification);
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease forwards';
      setTimeout(() => notification.remove(), 300);
    }, 3000);
    notification.querySelector('.notification-close')?.addEventListener('click', () => {
      notification.style.animation = 'slideOutRight 0.3s ease forwards';
      setTimeout(() => notification.remove(), 300);
    });
  }
});
// script.js

document.addEventListener('DOMContentLoaded', function() {
    const shippingSelect = document.getElementById('shipping-select');
    const deliveryAddress = document.getElementById('delivery-address');
    const addressField = document.getElementById('address');
    const paymentMethod = document.getElementById('payment-method');
    const cardDetails = document.getElementById('card-details');
    const checkoutForm = document.getElementById('checkout-form');
    const placeOrderBtn = document.getElementById('place-order-btn');

    if (shippingSelect && deliveryAddress && addressField) {
        shippingSelect.addEventListener('change', function() {
            deliveryAddress.style.display = (this.value === 'delivery') ? 'block' : 'none';
            addressField.required = (this.value === 'delivery');
        });
    }

    if (paymentMethod && cardDetails) {
        paymentMethod.addEventListener('change', function() {
            cardDetails.style.display = (this.value === 'card') ? 'block' : 'none';
        });
    }

    if (checkoutForm && placeOrderBtn) {
        checkoutForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const shipping = shippingSelect.value;
            const address = addressField?.value.trim();
            const payMethod = paymentMethod.value;
            const cardNum = document.getElementById('card_number')?.value.trim();
            const cardExp = document.getElementById('card_expiry')?.value.trim();
            const cardCvv = document.getElementById('card_cvv')?.value.trim();

            // Client-side validation
            if (shipping === 'delivery' && !address) {
                showNotification('error', 'Delivery address is required.');
                return;
            }
            if (payMethod === 'card') {
                if (!cardNum || !/^\d{16}$/.test(cardNum)) {
                    showNotification('error', 'Valid 16-digit card number is required.');
                    return;
                }
                if (!cardExp || !/^\d{2}\/\d{2}$/.test(cardExp)) {
                    showNotification('error', 'Valid expiry date (MM/YY) is required.');
                    return;
                }
                if (!cardCvv || !/^\d{3}$/.test(cardCvv)) {
                    showNotification('error', 'Valid 3-digit CVV is required.');
                    return;
                }
            }

            // Show loading state
            placeOrderBtn.disabled = true;
            placeOrderBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            // AJAX submission
            const formData = new FormData(checkoutForm);
            fetch('checkout.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('success', data.message);
                    setTimeout(() => {
                        window.location.href = `orders.php?order_id=${data.order_id}&success=1`;
                    }, 2000);
                } else {
                    showNotification('error', data.message);
                    placeOrderBtn.disabled = false;
                    placeOrderBtn.innerHTML = 'Place Order';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'An error occurred. Please try again.');
                placeOrderBtn.disabled = false;
                placeOrderBtn.innerHTML = 'Place Order';
            });
        });
    }

    // Helper function to show notifications
    function showNotification(type, message) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 5000);
    }
});
// staff jsMobile Menu Toggle
document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
    const mainNav = document.getElementById('main-nav');
    if (mainNav) mainNav.classList.toggle('active');
});

// Smooth Scroll for Nav Links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
            const mainNav = document.getElementById('main-nav');
            if (mainNav?.classList.contains('active')) {
                mainNav.classList.remove('active');
            }
        }
    });
});

// Delete Confirmation
document.addEventListener('DOMContentLoaded', () => {
    const deleteLinks = document.querySelectorAll('.delete-link');
    deleteLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            if (!confirm('Are you sure you want to delete this review?')) {
                e.preventDefault();
            }
        });
    });

    // Form Validation for Staff Forms
    const forms = document.querySelectorAll('.staff-form');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.border = '1px solid red';
                } else {
                    field.style.border = '1px solid #ddd';
                }
            });
            if (!valid) {
                e.preventDefault();
                alert('Please fill all required fields.');
            }
            // File upload validation
            if (form.querySelector('input[type="file"]') && form.querySelector('input[type="file"]').files.length === 0) {
                e.preventDefault();
                alert('Please upload an image.');
            }
        });
    });

    // AJAX for order status updates (optional enhancement)
    const orderForms = document.querySelectorAll('form[action]');
    orderForms.forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            fetch(window.location.href, {
                method: 'POST',
                body: new FormData(form)
            }).then(res => res.json()).then(data => {
                if (data.success) location.reload();
                else alert(data.message);
            });
        });
    });
});

// Notification System
function showNotification(type, message) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
}
// Contact Form Validation and Submission
const contactForm = document.getElementById('contact-form');
if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('contact.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            showNotification(data.status || 'success', data.message || 'Message sent!');
            if (data.status !== 'error') this.reset();
        })
        .catch(err => {
            console.error('Contact form error:', err);
            showNotification('error', 'Failed to send message.');
        });
    });
}