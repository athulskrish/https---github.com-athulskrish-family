// Enhanced Dynamic Family Tree JavaScript
let currentZoom = 1;
const MIN_ZOOM = 0.2;
const MAX_ZOOM = 5;
let isDragging = false;
let dragStartX = 0;
let dragStartY = 0;
let containerStartScrollLeft = 0;
let containerStartScrollTop = 0;

// Enhanced zoom function with smooth transitions
function zoomTree(factor) {
    currentZoom *= factor;
    // Limit zoom range
    currentZoom = Math.min(Math.max(MIN_ZOOM, currentZoom), MAX_ZOOM);
    
    const tree = document.querySelector('.family-tree');
    if (tree) {
        tree.style.transform = `scale(${currentZoom})`;
        tree.style.transformOrigin = 'center top';
        
        // Update zoom buttons state
        updateZoomButtons();
        
        // Store zoom level in localStorage
        localStorage.setItem('familyTreeZoom', currentZoom.toString());
    }
}

// Reset zoom to default with animation
function resetZoom() {
    currentZoom = 1;
    const tree = document.querySelector('.family-tree');
    if (tree) {
        tree.style.transition = 'transform 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
        tree.style.transform = 'scale(1)';
        
        setTimeout(() => {
            tree.style.transition = 'transform 0.3s ease';
        }, 500);
        
        updateZoomButtons();
        localStorage.removeItem('familyTreeZoom');
    }
}

// Enhanced update zoom button states
function updateZoomButtons() {
    const zoomInBtn = document.querySelector('.tree-controls button[onclick*="zoomTree(1.1)"]');
    const zoomOutBtn = document.querySelector('.tree-controls button[onclick*="zoomTree(0.9)"]');
    
    if (zoomInBtn) {
        zoomInBtn.disabled = currentZoom >= MAX_ZOOM;
        zoomInBtn.style.opacity = currentZoom >= MAX_ZOOM ? '0.5' : '1';
        zoomInBtn.title = `Zoom In (${Math.round(currentZoom * 100)}%)`;
    }
    
    if (zoomOutBtn) {
        zoomOutBtn.disabled = currentZoom <= MIN_ZOOM;
        zoomOutBtn.style.opacity = currentZoom <= MIN_ZOOM ? '0.5' : '1';
        zoomOutBtn.title = `Zoom Out (${Math.round(currentZoom * 100)}%)`;
    }
    
    // Update reset button
    const resetBtn = document.querySelector('.tree-controls button[onclick*="resetZoom"]');
    if (resetBtn) {
        resetBtn.title = `Reset Zoom (${Math.round(currentZoom * 100)}%)`;
    }
}

// Enhanced auto-fit function for unlimited generations
function autoFitTree() {
    const container = document.querySelector('.family-tree-container');
    const tree = document.querySelector('.family-tree');
    
    if (!container || !tree) return;
    
    // Reset transform to get actual dimensions
    const originalTransform = tree.style.transform;
    tree.style.transform = 'scale(1)';
    
    const containerWidth = container.clientWidth;
    const containerHeight = container.clientHeight;
    const treeWidth = tree.scrollWidth;
    const treeHeight = tree.scrollHeight;
    
    // Calculate scale factors with padding
    const padding = 60; // Padding in pixels
    const scaleX = (containerWidth - padding) / treeWidth;
    const scaleY = (containerHeight - padding) / treeHeight;
    
    // Use the smaller scale to ensure everything fits
    currentZoom = Math.min(scaleX, scaleY, 1.2); // Max 120% zoom for auto-fit
    currentZoom = Math.max(currentZoom, MIN_ZOOM); // Don't go below minimum
    
    tree.style.transform = `scale(${currentZoom})`;
    
    // Center the tree after auto-fit
    setTimeout(() => {
        centerTree();
    }, 100);
    
    updateZoomButtons();
}

// Center the tree in the viewport
function centerTree() {
    const container = document.querySelector('.family-tree-container');
    const tree = document.querySelector('.family-tree');
    
    if (!container || !tree) return;
    
    const containerRect = container.getBoundingClientRect();
    const treeRect = tree.getBoundingClientRect();
    
    const centerX = (containerRect.width - treeRect.width) / 2;
    const centerY = (containerRect.height - treeRect.height) / 2;
    
    container.scrollLeft = Math.max(0, (treeRect.width - containerRect.width) / 2);
    container.scrollTop = Math.max(0, (treeRect.height - containerRect.height) / 2);
}

// Enhanced smooth scrolling to specific member
function smoothScrollToMember(memberId) {
    const memberCard = document.querySelector(`[data-member-id="${memberId}"]`);
    if (memberCard) {
        memberCard.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
            inline: 'center'
        });
        
        // Enhanced highlight effect
        memberCard.classList.add('highlighted');
        setTimeout(() => {
            memberCard.classList.remove('highlighted');
        }, 3000);
        
        // Pulse effect
        memberCard.style.animation = 'memberPulse 0.6s ease-in-out';
        setTimeout(() => {
            memberCard.style.animation = '';
        }, 600);
    }
}

// Find and scroll to a member by name
function findMemberByName(searchTerm) {
    const memberCards = document.querySelectorAll('.member-card');
    const matches = [];
    
    memberCards.forEach(card => {
        const nameElement = card.querySelector('.member-name');
        if (nameElement && nameElement.textContent.toLowerCase().includes(searchTerm.toLowerCase())) {
            matches.push(card);
        }
    });
    
    if (matches.length === 1) {
        const memberId = matches[0].dataset.memberId;
        smoothScrollToMember(memberId);
    } else if (matches.length > 1) {

        // Could implement a selection dialog here
    } else {

    }
    
    return matches;
}

// Initialize enhanced tree functionality
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.family-tree-container');
    
    if (container) {
        // Enhanced mouse wheel zoom support
        container.addEventListener('wheel', function(e) {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                const zoomFactor = e.deltaY > 0 ? 0.9 : 1.1;
                zoomTree(zoomFactor);
            }
        }, { passive: false });
        
        // Enhanced pan support with better performance
        container.addEventListener('mousedown', function(e) {
            if (e.button === 1 || (e.ctrlKey && e.button === 0) || e.button === 0) { // Middle mouse, Ctrl+Left, or Left click
                e.preventDefault();
                isDragging = true;
                dragStartX = e.clientX;
                dragStartY = e.clientY;
                containerStartScrollLeft = container.scrollLeft;
                containerStartScrollTop = container.scrollTop;
                container.style.cursor = 'grabbing';
                document.body.style.userSelect = 'none'; // Prevent text selection
            }
        });
        
        document.addEventListener('mousemove', function(e) {
            if (!isDragging) return;
            
            e.preventDefault();
            const deltaX = e.clientX - dragStartX;
            const deltaY = e.clientY - dragStartY;
            
            container.scrollLeft = containerStartScrollLeft - deltaX;
            container.scrollTop = containerStartScrollTop - deltaY;
        });
        
        document.addEventListener('mouseup', function(e) {
            if (isDragging) {
                isDragging = false;
                container.style.cursor = 'default';
                document.body.style.userSelect = '';
            }
        });
        
        // Enhanced touch support for mobile
        let touchStartX, touchStartY, touchStartTime;
        let isTouch = false;
        
        container.addEventListener('touchstart', function(e) {
            if (e.touches.length === 1) {
                isTouch = true;
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
                touchStartTime = Date.now();
                containerStartScrollLeft = container.scrollLeft;
                containerStartScrollTop = container.scrollTop;
            } else if (e.touches.length === 2) {
                // Two finger touch for potential zoom gesture
                e.preventDefault();
            }
        }, { passive: false });
        
        container.addEventListener('touchmove', function(e) {
            if (e.touches.length === 1 && isTouch) {
                e.preventDefault();
                const touchX = e.touches[0].clientX;
                const touchY = e.touches[0].clientY;
                const deltaX = touchStartX - touchX;
                const deltaY = touchStartY - touchY;
                
                container.scrollLeft = containerStartScrollLeft + deltaX;
                container.scrollTop = containerStartScrollTop + deltaY;
            }
        }, { passive: false });
        
        container.addEventListener('touchend', function(e) {
            const touchEndTime = Date.now();
            const touchDuration = touchEndTime - touchStartTime;
            
            // If it was a quick tap (less than 200ms), it might be a click
            if (touchDuration < 200 && isTouch) {
                // Let the click event handle it
            }
            
            isTouch = false;
        });
    }
    
    // Enhanced click handlers for member cards
    const memberCards = document.querySelectorAll('.member-card');
    memberCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Only handle click if we weren't dragging
            if (!isDragging) {
                e.stopPropagation();
                const memberId = this.dataset.memberId;
                if (memberId) {
                    // Navigate to member details page
                    window.location.href = 'member.php?id=' + memberId;
                }
            }
        });
        
        // Enhanced hover effects
        card.addEventListener('mouseenter', function() {
            if (!isDragging) {
                this.style.transform = 'translateY(-8px) scale(1.03)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            if (!isDragging) {
                this.style.transform = '';
            }
        });
    });
    
    // Load saved zoom level
    const savedZoom = localStorage.getItem('familyTreeZoom');
    if (savedZoom) {
        currentZoom = parseFloat(savedZoom);
        const tree = document.querySelector('.family-tree');
        if (tree) {
            tree.style.transform = `scale(${currentZoom})`;
        }
    }
    
    // Initialize zoom buttons state
    updateZoomButtons();
    
    // Auto-fit tree on load if it's too large
    setTimeout(() => {
        const tree = document.querySelector('.family-tree');
        const container = document.querySelector('.family-tree-container');
        if (tree && container) {
            if (tree.scrollWidth > container.clientWidth * 0.9 || 
                tree.scrollHeight > container.clientHeight * 0.9) {
                autoFitTree();
            } else {
                centerTree();
            }
        }
    }, 500);
    
    // Add search functionality (if search input exists)
    const searchInput = document.getElementById('member-search');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.trim();
            if (searchTerm.length > 2) {
                findMemberByName(searchTerm);
            }
        });
    }
});

// Enhanced keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Don't interfere with form inputs
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
        return;
    }
    
    switch(e.key) {
        case '=':
        case '+':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                zoomTree(1.2);
            }
            break;
        case '-':
        case '_':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                zoomTree(0.8);
            }
            break;
        case '0':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                resetZoom();
            }
            break;
        case 'f':
        case 'F':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                autoFitTree();
            }
            break;
        case 'c':
        case 'C':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                centerTree();
            }
            break;
        case 'Escape':
            // Reset any highlighting
            document.querySelectorAll('.member-card.highlighted').forEach(card => {
                card.classList.remove('highlighted');
            });
            break;
        // Arrow key navigation
        case 'ArrowLeft':
        case 'ArrowRight':
        case 'ArrowUp':
        case 'ArrowDown':
            if (e.shiftKey) {
                e.preventDefault();
                navigateTree(e.key);
            }
            break;
    }
});

// Tree navigation with arrow keys
function navigateTree(direction) {
    const container = document.querySelector('.family-tree-container');
    if (!container) return;
    
    const scrollAmount = 100;
    
    switch(direction) {
        case 'ArrowLeft':
            container.scrollLeft -= scrollAmount;
            break;
        case 'ArrowRight':
            container.scrollLeft += scrollAmount;
            break;
        case 'ArrowUp':
            container.scrollTop -= scrollAmount;
            break;
        case 'ArrowDown':
            container.scrollTop += scrollAmount;
            break;
    }
}

// Add CSS animations for better interactions
const style = document.createElement('style');
style.textContent = `
    @keyframes memberPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    @keyframes highlightGlow {
        0%, 100% { box-shadow: 0 6px 18px rgba(0,0,0,0.12); }
        50% { box-shadow: 0 8px 30px rgba(255, 193, 7, 0.6); }
    }
    
    .member-card.highlighted {
        animation: highlightGlow 2s ease-in-out;
        z-index: 1001;
    }
    
    .family-tree-container::-webkit-scrollbar {
        width: 12px;
        height: 12px;
    }
    
    .family-tree-container::-webkit-scrollbar-track {
        background: rgba(0,0,0,0.1);
        border-radius: 6px;
    }
    
    .family-tree-container::-webkit-scrollbar-thumb {
        background: rgba(0, 123, 255, 0.6);
        border-radius: 6px;
        transition: background 0.3s ease;
    }
    
    .family-tree-container::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 123, 255, 0.8);
    }
    
    /* Loading animation for tree rendering */
    .family-tree.loading {
        opacity: 0.5;
        pointer-events: none;
    }
    
    .family-tree.loading::after {
        content: 'Loading family tree...';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255, 255, 255, 0.9);
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        font-size: 16px;
        color: #007bff;
        font-weight: 600;
        z-index: 1000;
    }
`;
document.head.appendChild(style);

// Export functions for external use
window.FamilyTreeNav = {
    zoomTree,
    resetZoom,
    autoFitTree,
    centerTree,
    smoothScrollToMember,
    findMemberByName,
    navigateTree
};


