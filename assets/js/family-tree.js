// Family Tree JavaScript
let currentZoom = 1;
const MIN_ZOOM = 0.3;
const MAX_ZOOM = 3;

// Function to zoom the family tree
function zoomTree(factor) {
    currentZoom *= factor;
    // Limit zoom range
    currentZoom = Math.min(Math.max(MIN_ZOOM, currentZoom), MAX_ZOOM);
    
    const tree = document.querySelector('.family-tree');
    if (tree) {
        tree.style.transform = `scale(${currentZoom})`;
        
        // Update zoom buttons state
        updateZoomButtons();
    }
}

// Reset zoom to default
function resetZoom() {
    currentZoom = 1;
    const tree = document.querySelector('.family-tree');
    if (tree) {
        tree.style.transform = 'scale(1)';
        updateZoomButtons();
    }
}

// Update zoom button states
function updateZoomButtons() {
    const zoomInBtn = document.querySelector('.tree-controls button[onclick="zoomTree(1.1)"]');
    const zoomOutBtn = document.querySelector('.tree-controls button[onclick="zoomTree(0.9)"]');
    
    if (zoomInBtn) {
        zoomInBtn.disabled = currentZoom >= MAX_ZOOM;
        zoomInBtn.style.opacity = currentZoom >= MAX_ZOOM ? '0.5' : '1';
    }
    
    if (zoomOutBtn) {
        zoomOutBtn.disabled = currentZoom <= MIN_ZOOM;
        zoomOutBtn.style.opacity = currentZoom <= MIN_ZOOM ? '0.5' : '1';
    }
}

// Auto-fit tree to container
function autoFitTree() {
    const container = document.querySelector('.family-tree-container');
    const tree = document.querySelector('.family-tree');
    
    if (!container || !tree) return;
    
    const containerWidth = container.clientWidth;
    const containerHeight = container.clientHeight;
    const treeWidth = tree.scrollWidth;
    const treeHeight = tree.scrollHeight;
    
    const scaleX = (containerWidth - 40) / treeWidth;  // 40px for padding
    const scaleY = (containerHeight - 40) / treeHeight;
    
    currentZoom = Math.min(scaleX, scaleY, 1); // Don't scale up beyond 100%
    tree.style.transform = `scale(${currentZoom})`;
    
    updateZoomButtons();
}

// Add smooth scrolling for better navigation
function smoothScrollToMember(memberId) {
    const memberCard = document.querySelector(`[data-member-id="${memberId}"]`);
    if (memberCard) {
        memberCard.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
            inline: 'center'
        });
        
        // Highlight the member temporarily
        memberCard.classList.add('highlighted');
        setTimeout(() => {
            memberCard.classList.remove('highlighted');
        }, 2000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.family-tree-container');
    
    if (container) {
        // Add mouse wheel zoom support
        container.addEventListener('wheel', function(e) {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                const zoomFactor = e.deltaY > 0 ? 0.9 : 1.1;
                zoomTree(zoomFactor);
            }
        });
        
        // Add pan support for mobile and desktop
        let isPanning = false;
        let startX, startY, scrollLeft, scrollTop;
        
        container.addEventListener('mousedown', function(e) {
            if (e.button === 1 || (e.ctrlKey && e.button === 0)) { // Middle mouse or Ctrl+Left click
                e.preventDefault();
                isPanning = true;
                startX = e.pageX - container.offsetLeft;
                startY = e.pageY - container.offsetTop;
                scrollLeft = container.scrollLeft;
                scrollTop = container.scrollTop;
                container.style.cursor = 'move';
            }
        });
        
        container.addEventListener('mouseleave', function() {
            isPanning = false;
            container.style.cursor = 'default';
        });
        
        container.addEventListener('mouseup', function() {
            isPanning = false;
            container.style.cursor = 'default';
        });
        
        container.addEventListener('mousemove', function(e) {
            if (!isPanning) return;
            e.preventDefault();
            const x = e.pageX - container.offsetLeft;
            const y = e.pageY - container.offsetTop;
            const walkX = (x - startX) * 2;
            const walkY = (y - startY) * 2;
            container.scrollLeft = scrollLeft - walkX;
            container.scrollTop = scrollTop - walkY;
        });
        
        // Touch support for mobile
        let touchStartX, touchStartY;
        container.addEventListener('touchstart', function(e) {
            if (e.touches.length === 1) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
            }
        });
        
        container.addEventListener('touchmove', function(e) {
            if (e.touches.length === 1) {
                e.preventDefault();
                const touchX = e.touches[0].clientX;
                const touchY = e.touches[0].clientY;
                const deltaX = touchStartX - touchX;
                const deltaY = touchStartY - touchY;
                
                container.scrollLeft += deltaX;
                container.scrollTop += deltaY;
                
                touchStartX = touchX;
                touchStartY = touchY;
            }
        });
    }
    
    // Add click handlers to member cards
    const memberCards = document.querySelectorAll('.member-card');
    memberCards.forEach(card => {
        card.addEventListener('click', function(e) {
            e.stopPropagation();
            const memberId = this.dataset.memberId;
            if (memberId) {
                // Navigate to member details page (using existing member.php)
                window.location.href = 'member.php?id=' + memberId;
            }
        });
        
        // Add hover effect to indicate clickability
        card.style.cursor = 'pointer';
        card.title = 'Click to view details';
    });
    
    // Initialize zoom buttons state
    updateZoomButtons();
    
    // Auto-fit tree on load if it's too large
    setTimeout(() => {
        const tree = document.querySelector('.family-tree');
        if (tree && tree.scrollWidth > window.innerWidth) {
            autoFitTree();
        }
    }, 100);
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        return; // Don't interfere with form inputs
    }
    
    switch(e.key) {
        case '=':
        case '+':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                zoomTree(1.1);
            }
            break;
        case '-':
        case '_':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                zoomTree(0.9);
            }
            break;
        case '0':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                resetZoom();
            }
            break;
        case 'f':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                autoFitTree();
            }
            break;
    }
});
