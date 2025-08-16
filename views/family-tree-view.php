<?php
// Ensure this file is included from family-tree-manager.php
defined('BASEPATH') or exit('No direct script access allowed');
?>

<div class="container-fluid">
    <h1 class="text-center my-4 text-white">
        <i class="fas fa-tree"></i> Family Tree Manager
    </h1>
    
    <!-- Enhanced Statistics Panel with Tree Controls -->
    <div class="stats-panel text-center mb-4">
        <div class="row">
            <div class="col-md-2">
                <h4><?php echo count($family_data['members']); ?></h4>
                <small>Total Members</small>
            </div>
            <div class="col-md-2">
                <h4><?php echo count(array_filter($family_data['members'], function($m) { return strtoupper($m['gender'] ?? '') == 'M'; })); ?></h4>
                <small>Males</small>
            </div>
            <div class="col-md-2">
                <h4><?php echo count(array_filter($family_data['members'], function($m) { return strtoupper($m['gender'] ?? '') == 'F'; })); ?></h4>
                <small>Females</small>
            </div>
            <div class="col-md-2">
                <h4><?php echo count($family_data['relationships']) / 2; ?></h4>
                <small>Relationships</small>
            </div>
            <div class="col-md-2">
                <h4><?php echo count(array_filter($family_data['members'], function($m) { return empty($m['date_of_death']) && ($m['is_living'] ?? true); })); ?></h4>
                <small>Living</small>
            </div>
            <div class="col-md-2">
                <h4 id="generation-count">-</h4>
                <small>Generations</small>
            </div>
        </div>
        
        <!-- Tree Navigation Controls -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="btn-group" role="group" aria-label="Tree Controls">
                    <button type="button" class="btn btn-light btn-sm" onclick="FamilyTreeNav.autoFitTree()" title="Fit tree to screen">
                        <i class="fas fa-compress-arrows-alt"></i> Fit to Screen
                    </button>
                    <button type="button" class="btn btn-light btn-sm" onclick="FamilyTreeNav.centerTree()" title="Center tree">
                        <i class="fas fa-crosshairs"></i> Center
                    </button>
                    <button type="button" class="btn btn-light btn-sm" onclick="FamilyTreeNav.resetZoom()" title="Reset zoom">
                        <i class="fas fa-expand-arrows-alt"></i> Reset Zoom
                    </button>
                    <button type="button" class="btn btn-light btn-sm" onclick="toggleFullscreen()" title="Toggle fullscreen">
                        <i class="fas fa-expand"></i> Fullscreen
                    </button>
                </div>
                
                <!-- Quick Search -->
                <div class="d-inline-block ms-3">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="member-search" placeholder="Search member..." style="width: 200px;">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Member Form -->
    <div class="row justify-content-center mb-4">
        <div class="col-lg-10">
            <div class="add-member-form">
                <h3><i class="fas fa-user-plus"></i> Add Family Member</h3>
                
                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if(isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="family-tree.php" enctype="multipart/form-data">
                    <!-- Basic Information -->
                    <div class="form-section mb-4">
                        <h4>Basic Information</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name">
                            </div>
                            <div class="col-md-4">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                                                 <div class="row mt-3">
                             <div class="col-md-4">
                                 <label for="gender" class="form-label">Gender *</label>
                                 <select class="form-control" id="gender" name="gender" required>
                                     <option value="">Select Gender</option>
                                     <option value="M">Male</option>
                                     <option value="F">Female</option>
                                     <option value="O">Other</option>
                                 </select>
                             </div>
                             <div class="col-md-4">
                                 <label for="maiden_name" class="form-label">Maiden Name</label>
                                 <input type="text" class="form-control" id="maiden_name" name="maiden_name" placeholder="For married females">
                                 <small class="form-text text-muted">Only applicable for married females</small>
                             </div>
                             <div class="col-md-4">
                                 <label for="occupation" class="form-label">Occupation</label>
                                 <input type="text" class="form-control" id="occupation" name="occupation">
                             </div>
                         </div>
                         
                         <!-- Photo Upload -->
                         <div class="row mt-3">
                             <div class="col-md-6">
                                 <label for="photo" class="form-label">Photo</label>
                                 <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                 <small class="form-text text-muted">Accepted formats: JPG, PNG, GIF (Max 10MB)</small>
                             </div>
                             <div class="col-md-6">
                                 <div id="photo_preview" class="mt-2" style="display: none;">
                                     <img id="preview_img" src="" alt="Photo preview" style="max-width: 150px; max-height: 150px; border-radius: 10px;">
                                 </div>
                             </div>
                         </div>
                    </div>
                    
                    <!-- Dates and Places -->
                    <div class="form-section mb-4">
                        <h4>Dates and Places</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <label for="birth_date" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="birth_date" name="birth_date">
                            </div>
                            <div class="col-md-4">
                                <label for="birth_place" class="form-label">Birth Place</label>
                                <input type="text" class="form-control" id="birth_place" name="birth_place">
                            </div>
                            <div class="col-md-4">
                                <label for="is_living" class="form-label">Living Status</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="is_living" name="is_living" value="1" checked>
                                    <label class="form-check-label" for="is_living">
                                        Currently Living
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3" id="death_info" style="display: none;">
                            <div class="col-md-4">
                                <label for="death_date" class="form-label">Date of Death</label>
                                <input type="date" class="form-control" id="death_date" name="death_date">
                            </div>
                            <div class="col-md-4">
                                <label for="death_place" class="form-label">Death Place</label>
                                <input type="text" class="form-control" id="death_place" name="death_place">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Relationships -->
                    <div class="form-section mb-4">
                        <h4>Relationships</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <label for="relationship_type" class="form-label">Relationship Category</label>
                                <select class="form-control" id="relationship_type" name="relationship_type">
                                    <option value="">Select Category</option>
                                    <option value="parent">Parent</option>
                                    <option value="child">Child</option>
                                    <option value="sibling">Sibling</option>
                                    <option value="spouse">Spouse</option>
                                    <option value="grandparent">Grandparent</option>
                                    <option value="grandchild">Grandchild</option>
                                    <option value="aunt-uncle">Aunt/Uncle</option>
                                    <option value="niece-nephew">Niece/Nephew</option>
                                    <option value="cousin">Cousin</option>
                                    <option value="in-law">In-Law</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="relationship_subtype" class="form-label">Specific Relationship</label>
                                <select class="form-control" id="relationship_subtype" name="relationship_subtype">
                                    <option value="">Select Relationship</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="related_to" class="form-label">Related to</label>
                                <select class="form-control" id="related_to" name="related_to">
                                    <option value="">Select Family Member (or leave empty if first member)</option>
                                    <?php foreach($family_data['members'] as $member): ?>
                                        <option value="<?php echo $member['id']; ?>">
                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3" id="marriage_info" style="display: none;">
                            <div class="col-md-4">
                                <label for="marriage_date" class="form-label">Marriage Date</label>
                                <input type="date" class="form-control" id="marriage_date" name="marriage_date">
                            </div>
                            <div class="col-md-4">
                                <label for="marriage_place" class="form-label">Marriage Place</label>
                                <input type="text" class="form-control" id="marriage_place" name="marriage_place">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="form-section mb-4">
                        <h4>Additional Information</h4>
                        <div class="row">
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any additional information about this family member"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" name="add_member" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus"></i> Add Family Member
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Family Tree Visualization -->
    <div class="tree-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="fas fa-sitemap"></i> Family Tree Visualization</h3>
            <div class="text-muted">
                <small>
                    <i class="fas fa-info-circle"></i> 
                    Use Ctrl+Scroll to zoom, drag to pan, click members for details
                </small>
            </div>
        </div>
        
        <div class="family-tree">
            <?php 
            if(function_exists('renderFamilyTree')) {
                echo renderFamilyTree($family_data);
            } else {
                echo '<div class="alert alert-warning">Tree rendering function not found.</div>';
            }
            ?>
        </div>
    </div>
</div>

<!-- JavaScript for dynamic form behavior -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dynamic relationship dropdown
    const relationshipType = document.getElementById('relationship_type');
    const subtypeSelect = document.getElementById('relationship_subtype');
    const marriageInfo = document.getElementById('marriage_info');
    
    if(relationshipType) {
        relationshipType.addEventListener('change', function() {
            const category = this.value;
            
            // Clear previous options
            subtypeSelect.innerHTML = '<option value="">Select Relationship</option>';
            
            if(category) {
                const relationships = <?php echo json_encode(FamilyRelationships::$relationships); ?>;
                const categoryRelations = relationships[category] || {};
                
                for(const [value, label] of Object.entries(categoryRelations)) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    subtypeSelect.appendChild(option);
                }
                
                // Show marriage info for spouse category
                marriageInfo.style.display = category === 'spouse' ? 'block' : 'none';
            }
        });
    }
    
    // Toggle death fields based on living status
    const isLiving = document.getElementById('is_living');
    const deathInfo = document.getElementById('death_info');
    
    if(isLiving && deathInfo) {
        isLiving.addEventListener('change', function() {
            deathInfo.style.display = this.checked ? 'none' : 'block';
            if(this.checked) {
                document.getElementById('death_date').value = '';
                document.getElementById('death_place').value = '';
            }
        });
        
             // Initial state
     deathInfo.style.display = isLiving.checked ? 'none' : 'block';
 }
 
 // Photo preview functionality
 const photoInput = document.getElementById('photo');
 const photoPreview = document.getElementById('photo_preview');
 const previewImg = document.getElementById('preview_img');
 
 if(photoInput) {
     photoInput.addEventListener('change', function() {
         const file = this.files[0];
         if(file) {
             const reader = new FileReader();
             reader.onload = function(e) {
                 previewImg.src = e.target.result;
                 photoPreview.style.display = 'block';
             };
             reader.readAsDataURL(file);
         } else {
             photoPreview.style.display = 'none';
         }
     });
 }
 
 // Maiden name field visibility based on gender
 const genderSelect = document.getElementById('gender');
 const maidenNameField = document.getElementById('maiden_name');
 const maidenNameLabel = maidenNameField ? maidenNameField.previousElementSibling : null;
 
 if(genderSelect && maidenNameField) {
     genderSelect.addEventListener('change', function() {
         if(this.value === 'F') {
             maidenNameField.style.display = 'block';
             if(maidenNameLabel) maidenNameLabel.style.display = 'block';
         } else {
             maidenNameField.style.display = 'none';
             maidenNameField.value = '';
             if(maidenNameLabel) maidenNameLabel.style.display = 'none';
         }
     });
     
     // Initial state
     if(genderSelect.value !== 'F') {
         maidenNameField.style.display = 'none';
         if(maidenNameLabel) maidenNameLabel.style.display = 'none';
     }
 }
});

    // Fullscreen toggle functionality
    function toggleFullscreen() {
        const treeContainer = document.querySelector('.tree-container');
        
        if (!document.fullscreenElement) {
            treeContainer.requestFullscreen().then(() => {
                treeContainer.classList.add('fullscreen-mode');
                document.querySelector('button[onclick="toggleFullscreen()"] i').className = 'fas fa-compress';
            }).catch(err => {
                console.log('Error entering fullscreen:', err);
            });
        } else {
            document.exitFullscreen().then(() => {
                treeContainer.classList.remove('fullscreen-mode');
                document.querySelector('button[onclick="toggleFullscreen()"] i').className = 'fas fa-expand';
            });
        }
    }
    
    // Clear search functionality
    function clearSearch() {
        const searchInput = document.getElementById('member-search');
        if (searchInput) {
            searchInput.value = '';
            // Remove any search highlights
            document.querySelectorAll('.member-card.search-highlight').forEach(card => {
                card.classList.remove('search-highlight');
            });
        }
    }
    
    // Count and display generation information
    function updateGenerationCount() {
        const generations = document.querySelectorAll('.generation-level');
        const generationCount = generations.length;
        const countElement = document.getElementById('generation-count');
        if (countElement) {
            countElement.textContent = generationCount;
        }
        
        // Add generation info to each level
        generations.forEach((level, index) => {
            const label = level.querySelector('.generation-label');
            if (label) {
                label.setAttribute('title', `Generation ${index + 1} of ${generationCount}`);
            }
        });
    }
    
    // Initialize enhanced features
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            updateGenerationCount();
        }, 1000); // Allow tree to render first
        
        // Enhanced search functionality
        const searchInput = document.getElementById('member-search');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const searchTerm = e.target.value.trim();
                    
                    // Clear previous highlights
                    document.querySelectorAll('.member-card.search-highlight').forEach(card => {
                        card.classList.remove('search-highlight');
                    });
                    
                    if (searchTerm.length > 1) {
                        const matches = window.FamilyTreeNav ? window.FamilyTreeNav.findMemberByName(searchTerm) : [];
                        matches.forEach(card => {
                            card.classList.add('search-highlight');
                        });
                        
                        // If only one match, scroll to it
                        if (matches.length === 1) {
                            const memberId = matches[0].dataset.memberId;
                            if (window.FamilyTreeNav) {
                                window.FamilyTreeNav.smoothScrollToMember(memberId);
                            }
                        }
                    }
                }, 300);
            });
        }
        
        // Handle fullscreen changes
        document.addEventListener('fullscreenchange', function() {
            const treeContainer = document.querySelector('.tree-container');
            const button = document.querySelector('button[onclick="toggleFullscreen()"] i');
            
            if (document.fullscreenElement) {
                treeContainer.classList.add('fullscreen-mode');
                if (button) button.className = 'fas fa-compress';
            } else {
                treeContainer.classList.remove('fullscreen-mode');
                if (button) button.className = 'fas fa-expand';
            }
        });
    });
</script>

<style>
/* Additional styles for enhanced features */
.tree-container.fullscreen-mode {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 9999;
    background: white;
    padding: 20px;
}

.tree-container.fullscreen-mode .family-tree-container {
    height: calc(100vh - 100px);
}

.member-card.search-highlight {
    border-color: #ffc107 !important;
    box-shadow: 0 0 15px rgba(255, 193, 7, 0.5) !important;
    animation: searchPulse 1s ease-in-out infinite alternate;
}

@keyframes searchPulse {
    from { transform: scale(1); }
    to { transform: scale(1.02); }
}

.stats-panel .btn-group .btn {
    margin: 0 2px;
}

.stats-panel .input-group {
    max-width: 250px;
    margin: 0 auto;
}

/* Tree container improvements */
.tree-container {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    position: relative;
}

.tree-container h3 {
    color: #007bff;
    font-weight: 600;
    margin-bottom: 0;
}

.tree-container .text-muted {
    font-size: 0.85rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .stats-panel .btn-group {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .stats-panel .btn {
        margin-bottom: 5px;
        font-size: 0.8rem;
    }
    
    .tree-container h3 {
        font-size: 1.3rem;
    }
    
    .stats-panel .input-group {
        margin-top: 10px;
    }
}
</style>

<!-- Add family tree specific JavaScript -->
<script src="assets/js/family-tree.js"></script>
