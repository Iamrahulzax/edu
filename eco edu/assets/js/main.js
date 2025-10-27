// EcoEdu Main JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize theme
    initializeTheme();
    
    // Initialize animations
    initializeAnimations();
    
    // Initialize tooltips and popovers
    initializeBootstrapComponents();
    
    // Initialize form validations
    initializeFormValidations();
    
    // Initialize quiz functionality
    initializeQuizzes();
    
    // Initialize challenge functionality
    initializeChallenges();
    
    // Initialize notifications
    initializeNotifications();

    // Start live leaderboard updates if relevant
    startLeaderboardPolling();
});

// Theme Management
function initializeTheme() {
    const savedTheme = localStorage.getItem('ecoedu-theme') || 'light';
    setTheme(savedTheme);
    
    // Update theme icon
    updateThemeIcon(savedTheme);
}

function startLeaderboardPolling() {
    const personalTab = document.getElementById('personal');
    const globalTab = document.getElementById('global');
    if (!personalTab && !globalTab) {
        return;
    }

    const updateLeaderboard = () => {
        fetch('api/leaderboard_live.php', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const statsCard = document.querySelector('#personal .card.border-success .card-body');
            if (statsCard) {
                statsCard.querySelector('.badge.bg-success').textContent = `Rank #${data.user.rank ?? 'N/A'}`;
                statsCard.querySelector('.badge.bg-secondary').textContent = `Total ${data.user.total_students ?? 'N/A'}`;
                const pointsEl = statsCard.querySelector('p strong');
                if (pointsEl) {
                    pointsEl.parentElement.innerHTML = `<strong>Eco Points:</strong> ${Number(data.user.eco_points).toLocaleString()}`;
                }
                const tierBadge = statsCard.querySelector('.badge.text-uppercase');
                if (tierBadge) {
                    tierBadge.textContent = data.user.tier.name;
                    tierBadge.className = `badge bg-${data.user.tier.slug} text-uppercase`;
                }
            }

            const peersContainer = document.querySelector('#personal .card-body.p-0');
            if (peersContainer && data.personal_window && Array.isArray(data.personal_window.rows)) {
                peersContainer.innerHTML = data.personal_window.rows.map(peer => {
                    const isUser = peer.id === data.user.id;
                    return `
                    <div class="leaderboard-item ${isUser ? 'border border-success' : ''}">
                        <div class="leaderboard-rank ${isUser ? 'gold' : ''}">${peer.computed_rank}</div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">
                                ${peer.first_name} ${peer.last_name}
                                ${isUser ? '<span class="badge bg-success ms-2">You</span>' : ''}
                            </h6>
                            <small class="text-muted">
                                <i class="fas fa-school me-1"></i>${peer.school_name || 'Independent'}
                            </small>
                            <div class="mt-1">
                                <span class="badge bg-${peer.tier.slug} text-uppercase">Tier: ${peer.tier.name}</span>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="eco-points"><i class="fas fa-coins me-1"></i>${Number(peer.eco_points).toLocaleString()}</div>
                            <small class="text-muted d-block"><i class="fas fa-medal me-1"></i>${peer.total_badges} badges</small>
                            ${isUser ? '' : `<small class="text-muted">Δ ${(peer.eco_points - data.user.eco_points).toLocaleString()} pts</small>`}
                        </div>
                    </div>`;
                }).join('');
            }

            const globalContainer = document.querySelector('#global .card-body.p-0');
            if (globalContainer && Array.isArray(data.global_top)) {
                globalContainer.innerHTML = data.global_top.map((leader, index) => {
                    const topClass = index === 0 ? 'gold' : index === 1 ? 'silver' : index === 2 ? 'bronze' : '';
                    return `
                        <div class="leaderboard-item">
                            <div class="leaderboard-rank ${topClass}">${index + 1}</div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${leader.first_name} ${leader.last_name}</h6>
                                <small class="text-muted"><i class="fas fa-school me-1"></i>${leader.school_name || 'Independent'}</small>
                            </div>
                            <div class="text-end">
                                <div class="eco-points"><i class="fas fa-coins me-1"></i>${Number(leader.eco_points).toLocaleString()}</div>
                            </div>
                        </div>`;
                }).join('');
            }
        })
        .catch(() => {});
    };

    updateLeaderboard();
    setInterval(updateLeaderboard, 30000);
}

function toggleTheme() {
    const currentTheme = document.body.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
    updateThemeIcon(newTheme);
}

function setTheme(theme) {
    document.body.setAttribute('data-theme', theme);
    localStorage.setItem('ecoedu-theme', theme);
}

function updateThemeIcon(theme) {
    const themeIcon = document.getElementById('theme-icon');
    if (themeIcon) {
        themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// Animations
function initializeAnimations() {
    // Intersection Observer for scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
            }
        });
    }, observerOptions);
    
    // Observe all cards and sections
    document.querySelectorAll('.card, .stat-card, .leaderboard-item').forEach(el => {
        observer.observe(el);
    });
}

// Bootstrap Components
function initializeBootstrapComponents() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-mdb-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new mdb.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-mdb-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new mdb.Popover(popoverTriggerEl);
    });
}

// Form Validations
function initializeFormValidations() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
}

// Quiz Functionality
function initializeQuizzes() {
    const quizForms = document.querySelectorAll('.quiz-form');
    const headerTimer = document.querySelector('.quiz-timer-header');

    quizForms.forEach(form => {
        const questions = form.querySelectorAll('.quiz-question');
        const formTimer = form.querySelector('.quiz-timer');
        const targetTimers = [];
        const progressBar = document.getElementById('quiz-progress');
        const progressText = document.getElementById('progress-text');
        const answeredCount = document.getElementById('answered-count');

        if (headerTimer) {
            const limitAttr = headerTimer.dataset.timeLimit;
            if (limitAttr) {
                targetTimers.push(headerTimer);
            }
        }

        if (formTimer) {
            targetTimers.push(formTimer);
        }

        if (targetTimers.length) {
            const timeLimit = parseInt(targetTimers[0].dataset.timeLimit) || 300;
            startQuizTimer(targetTimers, timeLimit, form);
        }

        const updateQuizProgress = () => {
            const totalQuestions = questions.length;
            if (!totalQuestions) return;

            const answeredHidden = form.querySelectorAll('input[type="hidden"][name^="question_"][value!=""]').length;
            const answeredText = Array.from(form.querySelectorAll('.quiz-question input[type="text"]'))
                .filter(input => input.value.trim() !== '').length;
            const answered = answeredHidden + answeredText;
            const percentage = Math.round((answered / totalQuestions) * 100);

            if (progressBar) {
                progressBar.style.width = `${percentage}%`;
                progressBar.setAttribute('aria-valuenow', percentage.toString());
            }

            if (progressText) {
                progressText.textContent = `${percentage}%`;
            }

            if (answeredCount) {
                answeredCount.textContent = answered;
            }
        };

        // Handle question selection
        questions.forEach(question => {
            const options = question.querySelectorAll('.quiz-option');
            options.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from siblings
                    options.forEach(opt => opt.classList.remove('selected'));
                    // Add selected class to clicked option
                    this.classList.add('selected');

                    // Update hidden input value
                    const input = question.querySelector('input[type="hidden"]');
                    if (input) {
                        input.value = this.dataset.value;
                    }

                    updateQuizProgress();
                });
            });
        });

        // Track text / fill-in-the-blank inputs
        form.querySelectorAll('.quiz-question input[type="text"]').forEach(input => {
            input.addEventListener('input', updateQuizProgress);
        });

        // Warn when submitting with unanswered questions
        form.addEventListener('submit', event => {
            const totalQuestions = questions.length;
            const answeredHidden = form.querySelectorAll('input[type="hidden"][name^="question_"][value!=""]').length;
            const answeredText = Array.from(form.querySelectorAll('.quiz-question input[type="text"]'))
                .filter(input => input.value.trim() !== '').length;
            const answered = answeredHidden + answeredText;

            if (answered < totalQuestions) {
                const confirmed = window.confirm(`You have only answered ${answered} out of ${totalQuestions} questions. Are you sure you want to submit?`);
                if (!confirmed) {
                    event.preventDefault();
                }
            }
        });

        updateQuizProgress();
    });
}

function startQuizTimer(timerElements, timeLimit, form) {
    let timeLeft = timeLimit;

    const updateTimer = () => {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        const display = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        timerElements.forEach(timerElement => {
            timerElement.textContent = display;

            if (timeLeft <= 60) {
                timerElement.classList.add('text-danger');
                timerElement.classList.remove('text-warning');
            } else if (timeLeft <= 300) {
                timerElement.classList.add('text-warning');
                timerElement.classList.remove('text-danger');
            }
        });

        if (timeLeft <= 0) {
            if (form) {
                showNotification("Time's up! Quiz submitted automatically.", 'warning');
                form.submit();
            }
        } else {
            timeLeft--;
            setTimeout(updateTimer, 1000);
        }
    };

    updateTimer();
}

// Challenge Functionality
function initializeChallenges() {
    const challengeCards = document.querySelectorAll('.challenge-card');
    
    challengeCards.forEach(card => {
        const joinBtn = card.querySelector('.join-challenge-btn');
        if (joinBtn) {
            joinBtn.addEventListener('click', function() {
                const challengeId = this.dataset.challengeId;
                joinChallenge(challengeId);
            });
        }
    });
}

function joinChallenge(challengeId) {
    showLoading();
    
    fetch('api/join_challenge.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            challenge_id: challengeId,
            csrf_token: getCSRFToken()
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showNotification('Successfully joined challenge!', 'success');
            // Update button state
            const btn = document.querySelector(`[data-challenge-id="${challengeId}"]`);
            if (btn) {
                btn.textContent = 'Joined';
                btn.disabled = true;
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-success');
            }
        } else {
            showNotification(data.message || 'Failed to join challenge', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('Network error. Please try again.', 'error');
        console.error('Error:', error);
    });
}

// Notification System
function initializeNotifications() {
    // Check for URL parameters for notifications
    const urlParams = new URLSearchParams(window.location.search);
    const message = urlParams.get('message');
    const type = urlParams.get('type');
    
    if (message) {
        showNotification(decodeURIComponent(message), type || 'info');
        
        // Clean URL
        const url = new URL(window.location);
        url.searchParams.delete('message');
        url.searchParams.delete('type');
        window.history.replaceState({}, document.title, url);
    }
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${getAlertClass(type)} alert-dismissible fade show notification-toast`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 500px;
        animation: slideInRight 0.3s ease-out;
    `;
    
    notification.innerHTML = `
        <i class="fas ${getNotificationIcon(type)} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }
    }, 5000);
}

function getAlertClass(type) {
    const classes = {
        'success': 'success',
        'error': 'danger',
        'warning': 'warning',
        'info': 'info'
    };
    return classes[type] || 'info';
}

function getNotificationIcon(type) {
    const icons = {
        'success': 'fa-check-circle',
        'error': 'fa-exclamation-circle',
        'warning': 'fa-exclamation-triangle',
        'info': 'fa-info-circle'
    };
    return icons[type] || 'fa-info-circle';
}

// Loading States
function showLoading(element = null) {
    if (element) {
        const originalContent = element.innerHTML;
        element.dataset.originalContent = originalContent;
        element.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
        element.disabled = true;
    } else {
        // Show global loading
        const loading = document.createElement('div');
        loading.id = 'global-loading';
        loading.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
        loading.style.cssText = 'background: rgba(0,0,0,0.5); z-index: 10000;';
        loading.innerHTML = '<div class="spinner"></div>';
        document.body.appendChild(loading);
    }
}

function hideLoading(element = null) {
    if (element) {
        if (element.dataset.originalContent) {
            element.innerHTML = element.dataset.originalContent;
            delete element.dataset.originalContent;
        }
        element.disabled = false;
    } else {
        // Hide global loading
        const loading = document.getElementById('global-loading');
        if (loading) {
            loading.remove();
        }
    }
}

// Utility Functions
function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Copied to clipboard!', 'success');
    }).catch(() => {
        showNotification('Failed to copy to clipboard', 'error');
    });
}

// Progress Animation
function animateProgress(element, targetValue, duration = 1000) {
    const startValue = 0;
    const startTime = performance.now();
    
    function updateProgress(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const currentValue = startValue + (targetValue - startValue) * progress;
        element.style.width = currentValue + '%';
        element.setAttribute('aria-valuenow', currentValue);
        
        if (progress < 1) {
            requestAnimationFrame(updateProgress);
        }
    }
    
    requestAnimationFrame(updateProgress);
}

// Points Animation
function animatePoints(element, targetPoints, duration = 2000) {
    const startPoints = 0;
    const startTime = performance.now();
    
    function updatePoints(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const currentPoints = Math.floor(startPoints + (targetPoints - startPoints) * progress);
        element.textContent = formatNumber(currentPoints);
        
        if (progress < 1) {
            requestAnimationFrame(updatePoints);
        }
    }
    
    requestAnimationFrame(updatePoints);
}

// Badge Unlock Animation
function showBadgeUnlock(badgeName, badgeIcon) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="modal-body">
                    <div class="badge-unlock-animation">
                        <i class="${badgeIcon} fa-4x text-warning mb-3 pulse-animation"></i>
                        <h3 class="text-gradient">Badge Unlocked!</h3>
                        <h4>${badgeName}</h4>
                        <p class="text-muted">Congratulations on your achievement!</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-success" data-mdb-dismiss="modal">Awesome!</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const modalInstance = new mdb.Modal(modal);
    modalInstance.show();
    
    // Remove modal after it's hidden
    modal.addEventListener('hidden.mdb.modal', () => {
        modal.remove();
    });
}

// Level Up Animation
function showLevelUp(newLevel, levelIcon) {
    showNotification(`🎉 Level Up! You're now a ${newLevel}!`, 'success');
    
    // Add confetti effect
    createConfetti();
}

function createConfetti() {
    const colors = ['#28a745', '#20c997', '#17a2b8', '#ffc107', '#fd7e14'];
    const confettiCount = 50;
    
    for (let i = 0; i < confettiCount; i++) {
        const confetti = document.createElement('div');
        confetti.style.cssText = `
            position: fixed;
            width: 10px;
            height: 10px;
            background: ${colors[Math.floor(Math.random() * colors.length)]};
            top: -10px;
            left: ${Math.random() * 100}%;
            z-index: 10000;
            border-radius: 50%;
            animation: confetti-fall ${2 + Math.random() * 3}s linear forwards;
        `;
        
        document.body.appendChild(confetti);
        
        setTimeout(() => {
            confetti.remove();
        }, 5000);
    }
}

// Add confetti animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes confetti-fall {
        0% {
            transform: translateY(-100vh) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(100vh) rotate(720deg);
            opacity: 0;
        }
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Export functions for global use
window.EcoEdu = {
    toggleTheme,
    showNotification,
    showLoading,
    hideLoading,
    animateProgress,
    animatePoints,
    showBadgeUnlock,
    showLevelUp,
    joinChallenge
};
