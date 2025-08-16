// Main JavaScript file for Family Tree Maker

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Media gallery functionality
    async function viewMedia(mediaId) {
        const modal = document.getElementById('viewMediaModal');
        const modalBody = modal.querySelector('.modal-body');
        const modalTitle = modal.querySelector('.modal-title');
        
        // Show loading state
        modalBody.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        try {
            // Fetch media details
            const response = await fetch(`get-media.php?id=${mediaId}`);
            const media = await response.json();
            
            modalTitle.textContent = media.title;
            
            if (media.media_type === 'photo') {
                const img = new Image();
                img.className = 'img-fluid';
                img.alt = media.title;
                
                // Create placeholder while image loads
                modalBody.innerHTML = `
                    <div class="photo-placeholder">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    ${media.description ? `<p class="mt-3">${media.description}</p>` : ''}
                `;
                
                // Load image
                img.onload = async function() {
                    modalBody.querySelector('.photo-placeholder').replaceWith(img);
                    await TreeCache.cacheMediaUrl(media.file_url);
                };
                img.src = media.file_url;
                
            } else if (media.media_type === 'video') {
                modalBody.innerHTML = `
                    <video controls class="w-100" preload="metadata">
                        <source src="${media.file_url}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    ${media.description ? `<p class="mt-3">${media.description}</p>` : ''}
                `;
                
                // Cache video metadata
                await TreeCache.cacheMediaUrl(media.file_url);
                
            } else {
                modalBody.innerHTML = `
                    <div class="text-center">
                        <a href="${media.file_url}" class="btn btn-primary btn-lg" target="_blank">
                            <i class="fas fa-file-download"></i> Download Document
                        </a>
                    </div>
                    ${media.description ? `<p class="mt-3">${media.description}</p>` : ''}
                `;
            }
        } catch (error) {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    Failed to load media. Please try again later.
                </div>
            `;
        }

        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
    }
});

// Initialize popovers
var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl);
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        var bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Form validation
var forms = document.querySelectorAll('.needs-validation');
Array.prototype.slice.call(forms).forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
});

// Family Tree visualization
let treeContainer = null;
let zoom = 1;
const zoomStep = 0.1;
let isDragging = false;
let startX = 0;
let startY = 0;
let scrollLeft = 0;
let scrollTop = 0;

// Cache configuration
const CACHE_VERSION = '1.0';
const CACHE_KEYS = {
    TREE_DATA: 'familyTree_data',
    MEDIA_URLS: 'familyTree_media',
    LAYOUT_STATE: 'familyTree_layout'
};

// Cache management
class TreeCache {
    static async init() {
        if ('caches' in window) {
            const cache = await caches.open('family-tree-cache-' + CACHE_VERSION);
            return cache;
        }
        return null;
    }

    static async saveTreeData(data) {
        try {
            localStorage.setItem(CACHE_KEYS.TREE_DATA, JSON.stringify({
                timestamp: Date.now(),
                data: data
            }));
        } catch (e) {
            console.warn('Failed to cache tree data:', e);
        }
    }

    static async getTreeData() {
        try {
            const cached = localStorage.getItem(CACHE_KEYS.TREE_DATA);
            if (cached) {
                const { timestamp, data } = JSON.parse(cached);
                // Cache valid for 1 hour
                if (Date.now() - timestamp < 3600000) {
                    return data;
                }
            }
        } catch (e) {
            console.warn('Failed to retrieve cached tree data:', e);
        }
        return null;
    }

    static async cacheMediaUrl(url) {
        const cache = await this.init();
        if (cache) {
            try {
                await cache.add(url);
            } catch (e) {
                console.warn('Failed to cache media:', e);
            }
        }
    }

    static saveLayoutState() {
        if (treeContainer) {
            const state = {
                zoom,
                scrollLeft: treeContainer.scrollLeft,
                scrollTop: treeContainer.scrollTop,
                timestamp: Date.now()
            };
            localStorage.setItem(CACHE_KEYS.LAYOUT_STATE, JSON.stringify(state));
        }
    }

    static loadLayoutState() {
        try {
            const state = JSON.parse(localStorage.getItem(CACHE_KEYS.LAYOUT_STATE));
            if (state && Date.now() - state.timestamp < 86400000) { // 24 hours
                zoom = state.zoom;
                if (treeContainer) {
                    treeContainer.scrollLeft = state.scrollLeft;
                    treeContainer.scrollTop = state.scrollTop;
                }
                return true;
            }
        } catch (e) {
            console.warn('Failed to load layout state:', e);
        }
        return false;
    }
}

async function initializeFamilyTree(members) {
    treeContainer = document.getElementById('family-tree-container');
    
    // Try to load cached tree data first
    const cachedData = await TreeCache.getTreeData();
    if (cachedData) {
        renderFamilyTree(cachedData);
    } else {
        renderFamilyTree(members);
        await TreeCache.saveTreeData(members);
    }
    
    initializeDragAndDrop();
    
    // Restore previous layout state if available
    TreeCache.loadLayoutState();
    
    // Save layout state when user leaves the page
    window.addEventListener('beforeunload', () => {
        TreeCache.saveLayoutState();
    });
    
    // Periodically save layout state
    setInterval(() => {
        TreeCache.saveLayoutState();
    }, 30000); // Every 30 seconds
}

function initializeDragAndDrop() {
    treeContainer.style.cursor = 'grab';
    
    treeContainer.addEventListener('mousedown', (e) => {
        isDragging = true;
        treeContainer.style.cursor = 'grabbing';
        startX = e.pageX - treeContainer.offsetLeft;
        startY = e.pageY - treeContainer.offsetTop;
        scrollLeft = treeContainer.scrollLeft;
        scrollTop = treeContainer.scrollTop;
    });

    treeContainer.addEventListener('mouseleave', () => {
        isDragging = false;
        treeContainer.style.cursor = 'grab';
    });

    treeContainer.addEventListener('mouseup', () => {
        isDragging = false;
        treeContainer.style.cursor = 'grab';
    });

    treeContainer.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        const x = e.pageX - treeContainer.offsetLeft;
        const y = e.pageY - treeContainer.offsetTop;
        const moveX = (x - startX) * (2/zoom);
        const moveY = (y - startY) * (2/zoom);
        treeContainer.scrollLeft = scrollLeft - moveX;
        treeContainer.scrollTop = scrollTop - moveY;
    });

    // Touch events for mobile support
    treeContainer.addEventListener('touchstart', (e) => {
        if (e.touches.length === 1) {
            isDragging = true;
            startX = e.touches[0].pageX - treeContainer.offsetLeft;
            startY = e.touches[0].pageY - treeContainer.offsetTop;
            scrollLeft = treeContainer.scrollLeft;
            scrollTop = treeContainer.scrollTop;
        }
    }, { passive: false });

    treeContainer.addEventListener('touchend', () => {
        isDragging = false;
    });

    treeContainer.addEventListener('touchmove', (e) => {
        if (!isDragging || e.touches.length !== 1) return;
        e.preventDefault();
        const x = e.touches[0].pageX - treeContainer.offsetLeft;
        const y = e.touches[0].pageY - treeContainer.offsetTop;
        const moveX = (x - startX) * (2/zoom);
        const moveY = (y - startY) * (2/zoom);
        treeContainer.scrollLeft = scrollLeft - moveX;
        treeContainer.scrollTop = scrollTop - moveY;
    }, { passive: false });

    // Pinch zoom support for mobile
    let lastTouchDistance = 0;
    treeContainer.addEventListener('touchstart', (e) => {
        if (e.touches.length === 2) {
            lastTouchDistance = Math.hypot(
                e.touches[0].pageX - e.touches[1].pageX,
                e.touches[0].pageY - e.touches[1].pageY
            );
        }
    });

    treeContainer.addEventListener('touchmove', (e) => {
        if (e.touches.length === 2) {
            e.preventDefault();
            const distance = Math.hypot(
                e.touches[0].pageX - e.touches[1].pageX,
                e.touches[0].pageY - e.touches[1].pageY
            );
            
            if (lastTouchDistance) {
                const delta = distance - lastTouchDistance;
                if (Math.abs(delta) > 10) {
                    zoom = Math.min(Math.max(zoom + (delta > 0 ? zoomStep : -zoomStep), 0.5), 2);
                    applyZoom();
                }
            }
            lastTouchDistance = distance;
        }
    }, { passive: false });
}

function renderFamilyTree(allMembers, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    // Find root members (those without parents)
    const rootMembers = allMembers.filter(member => 
        !member.parent_ids || member.parent_ids.trim() === ''
    );
    
    // Clear container
    container.innerHTML = '';
    
    // Create tree structure
    const treeContainer = document.createElement('div');
    treeContainer.className = 'family-tree-container';
    
    // Render each root member and their descendants
    rootMembers.forEach(rootMember => {
        const memberTree = createMemberTree(rootMember, allMembers, 0);
        treeContainer.appendChild(memberTree);
    });
    
    container.appendChild(treeContainer);
}

function createMemberTree(member, allMembers, level) {
    // Create member card
    const memberDiv = document.createElement('div');
    memberDiv.className = `family-member level-${level}`;
    memberDiv.innerHTML = `
        <div class="member-card">
            ${member.photo ? `<img src="${member.photo}" alt="${member.name}" class="member-photo">` : ''}
            <div class="member-info">
                <h4>${member.name}</h4>
                ${member.birth_date ? `<p>Born: ${member.birth_date}</p>` : ''}
                ${member.death_date ? `<p>Died: ${member.death_date}</p>` : ''}
            </div>
        </div>
    `;
    
    // Find children
    const children = findChildrenOfGeneration([member], allMembers);
    
    if (children.length > 0) {
        const childrenContainer = document.createElement('div');
        childrenContainer.className = 'children-container';
        
        children.forEach(child => {
            const childTree = createMemberTree(child, allMembers, level + 1);
            childrenContainer.appendChild(childTree);
        });
        
        memberDiv.appendChild(childrenContainer);
    }
    
    return memberDiv;
}

function createMemberCard(member) {
    const card = document.createElement('div');
    card.className = 'member-card';
    card.setAttribute('data-member-id', member.id);
    
    // Add photo container
    const photoContainer = document.createElement('div');
    photoContainer.className = 'photo-container';
    
    if (member.photo_url) {
        // Create placeholder
        const placeholder = document.createElement('div');
        placeholder.className = 'photo-placeholder';
        const placeholderIcon = document.createElement('i');
        placeholderIcon.className = 'fas fa-user fa-3x mb-2';
        placeholder.appendChild(placeholderIcon);
        photoContainer.appendChild(placeholder);
        
        // Set up lazy loading
        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = new Image();
                    img.onload = async function() {
                        photoContainer.innerHTML = '';
                        photoContainer.appendChild(img);
                        // Cache the loaded image
                        await TreeCache.cacheMediaUrl(member.photo_url);
                    };
                    img.src = member.photo_url;
                    img.alt = `${member.first_name} ${member.last_name}`;
                    img.className = 'member-photo';
                    observer.disconnect();
                }
            });
        }, {
            rootMargin: '50px',
            threshold: 0.1
        });
        
        observer.observe(photoContainer);
    } else {
        // Default avatar icon
        const icon = document.createElement('i');
        icon.className = 'fas fa-user fa-3x mb-2';
        photoContainer.appendChild(icon);
    }
    
    card.appendChild(photoContainer);
    
    // Add member information
    const info = document.createElement('div');
    info.className = 'member-info';
    
    const name = document.createElement('h5');
    name.textContent = `${member.first_name} ${member.last_name}`;
    info.appendChild(name);
    
    if (member.date_of_birth) {
        const birth = document.createElement('p');
        birth.className = 'mb-0';
        birth.textContent = new Date(member.date_of_birth).getFullYear();
        if (member.date_of_death) {
            birth.textContent += ` - ${new Date(member.date_of_death).getFullYear()}`;
        }
        info.appendChild(birth);
    }
    
    card.appendChild(info);
    
    // Add click event for details
    card.addEventListener('click', () => showMemberDetails(member));
    
    return card;
}

function drawConnectionLine(parent, child) {
    const svg = document.getElementById('family-tree-svg');
    if (!svg) {
        // Create SVG container if it doesn't exist
        const svgContainer = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svgContainer.id = 'family-tree-svg';
        svgContainer.style.position = 'absolute';
        svgContainer.style.top = '0';
        svgContainer.style.left = '0';
        svgContainer.style.width = '100%';
        svgContainer.style.height = '100%';
        svgContainer.style.pointerEvents = 'none';
        treeContainer.insertBefore(svgContainer, treeContainer.firstChild);
    }

    const parentCard = document.querySelector(`[data-member-id="${parent.id}"]`);
    const childCard = document.querySelector(`[data-member-id="${child.id}"]`);

    if (!parentCard || !childCard) return;

    // Get positions of the cards
    const parentRect = parentCard.getBoundingClientRect();
    const childRect = childCard.getBoundingClientRect();
    const containerRect = treeContainer.getBoundingClientRect();

    // Calculate connection points
    const parentBottom = {
        x: parentRect.left + parentRect.width / 2 - containerRect.left,
        y: parentRect.bottom - containerRect.top
    };
    
    const childTop = {
        x: childRect.left + childRect.width / 2 - containerRect.left,
        y: childRect.top - containerRect.top
    };

    // Create path
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    
    // Calculate control points for the curve
    const midY = (parentBottom.y + childTop.y) / 2;
    
    // Create a curved path using cubic bezier
    const pathD = `M ${parentBottom.x} ${parentBottom.y} 
                   C ${parentBottom.x} ${midY},
                     ${childTop.x} ${midY},
                     ${childTop.x} ${childTop.y}`;

    path.setAttribute('d', pathD);
    path.setAttribute('stroke', '#007bff');
    path.setAttribute('stroke-width', '2');
    path.setAttribute('fill', 'none');
    
    // Add data attributes for relationship identification
    path.setAttribute('data-parent-id', parent.id);
    path.setAttribute('data-child-id', child.id);

    document.getElementById('family-tree-svg').appendChild(path);
}

function findChildrenOfGeneration(generation, allMembers) {
    const children = new Set();
    generation.forEach(member => {
        allMembers.forEach(potential => {
            if (potential.parent_ids && potential.parent_ids.split(',').includes(member.id.toString())) {
                children.add(potential);
            }
        });
    });
    return Array.from(children);
}

function showMemberDetails(member) {
    // Implementation of member details modal
    // This will show a modal with all member information and options to edit
}

function zoomIn() {
    zoom = Math.min(zoom + zoomStep, 2);
    applyZoom();
}

function zoomOut() {
    zoom = Math.max(zoom - zoomStep, 0.5);
    applyZoom();
}

function resetZoom() {
    zoom = 1;
    applyZoom();
}

function applyZoom() {
    treeContainer.style.transform = `scale(${zoom})`;
    
    // Update SVG lines after zoom
    const observer = new MutationObserver((mutations) => {
        updateAllConnections();
    });
    
    observer.observe(treeContainer, {
        attributes: true,
        attributeFilter: ['style']
    });
}

function updateAllConnections() {
    const svg = document.getElementById('family-tree-svg');
    if (svg) {
        svg.innerHTML = ''; // Clear existing lines
        
        // Redraw all connections
        const members = getAllMembers();
        members.forEach(member => {
            if (member.parent_ids) {
                const parents = member.parent_ids.split(',');
                parents.forEach(parentId => {
                    if (parentId) {
                        const parent = getMemberById(parentId);
                        if (parent) {
                            drawConnectionLine(parent, member);
                        }
                    }
                });
            }
        });
    }
}

function getAllMembers() {
    const memberCards = document.querySelectorAll('.member-card');
    return Array.from(memberCards).map(card => {
        return {
            id: card.getAttribute('data-member-id'),
            parent_ids: card.getAttribute('data-parent-ids')
        };
    });
}

function getMemberById(id) {
    const card = document.querySelector(`[data-member-id="${id}"]`);
    return card ? { id } : null;
}

// Relationship management
let relationshipCounter = 0;

function addRelationship() {
    const container = document.getElementById('relationships-container');
    
    if (!container) {
        console.error('relationships-container not found');
        return;
    }
    
    const relationshipDiv = document.createElement('div');
    relationshipDiv.className = 'relationship-entry border rounded p-3 mb-2';
    
    const memberOptions = generateMemberOptions();

    
    relationshipDiv.innerHTML = `
        <div class="row">
            <div class="col-md-5">
                <select class="form-select" name="relationship_type[]" required>
                    <option value="">Select type...</option>
                    <option value="parent-child">Parent</option>
                    <option value="spouse">Spouse</option>
                    <option value="sibling">Sibling</option>
                </select>
            </div>
            <div class="col-md-5">
                <select class="form-select" name="related_person[]" required>
                    <option value="">Select person...</option>
                    ${memberOptions}
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRelationship(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(relationshipDiv);
    relationshipCounter++;

}

function removeRelationship(button) {
    button.closest('.relationship-entry').remove();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function generateMemberOptions() {


    
    const list = Array.isArray(window.treeMembers) ? window.treeMembers : [];

    
    const options = list.map(function(member) {
        const fullName = `${escapeHtml(member.first_name)} ${escapeHtml(member.last_name)}`.trim();
        return `<option value="${member.id}">${fullName}</option>`;
    }).join('');
    

    return options;
}

// Add to your existing main.js

function createMemberForm() {
    return `
        <form id="memberForm" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            
            <div class="mb-3">
                <label for="maiden_name" class="form-label">Maiden Name (if applicable)</label>
                <input type="text" class="form-control" id="maiden_name" name="maiden_name">
            </div>
            
            <div class="mb-3">
                <label for="photo" class="form-label">Photo</label>
                <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                <div class="form-text">Max file size: 5MB. Accepted formats: JPG, PNG, GIF</div>
            </div>
            
            <!-- ... other existing fields ... -->
            
            <button type="submit" class="btn btn-primary">Add Member</button>
        </form>
    `;
}

// Handle form submission with photo upload
$(document).on('submit', '#memberForm', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_member');
    
    $.ajax({
        url: 'add_member.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Member added successfully!');
                location.reload(); // Refresh to show new member
            } else {
                alert('Error: ' + result.error);
            }
        },
        error: function() {
            alert('Upload failed. Please try again.');
        }
    });
});
