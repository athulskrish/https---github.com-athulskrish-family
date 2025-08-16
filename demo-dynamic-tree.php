<?php
/**
 * Dynamic Family Tree Demo
 * Showcases unlimited generation support with sample data
 */
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Create sample data for demonstration
$sample_family_data = [
    'members' => [
        // Generation 0 - Great Great Grandparents
        ['id' => 1, 'first_name' => 'William', 'last_name' => 'Anderson', 'gender' => 'M', 'date_of_birth' => '1890-03-15', 'date_of_death' => '1965-08-22'],
        ['id' => 2, 'first_name' => 'Mary', 'last_name' => 'Anderson', 'maiden_name' => 'Thompson', 'gender' => 'F', 'date_of_birth' => '1895-07-10', 'date_of_death' => '1970-12-05'],
        
        ['id' => 3, 'first_name' => 'Charles', 'last_name' => 'Wilson', 'gender' => 'M', 'date_of_birth' => '1888-11-20', 'date_of_death' => '1960-04-18'],
        ['id' => 4, 'first_name' => 'Elizabeth', 'last_name' => 'Wilson', 'maiden_name' => 'Davis', 'gender' => 'F', 'date_of_birth' => '1892-02-28', 'date_of_death' => '1968-09-30'],
        
        // Generation 1 - Great Grandparents  
        ['id' => 5, 'first_name' => 'Robert', 'last_name' => 'Anderson', 'gender' => 'M', 'date_of_birth' => '1920-06-12', 'date_of_death' => '1995-03-08'],
        ['id' => 6, 'first_name' => 'Helen', 'last_name' => 'Anderson', 'maiden_name' => 'Wilson', 'gender' => 'F', 'date_of_birth' => '1925-01-30', 'date_of_death' => '2000-11-15'],
        
        ['id' => 7, 'first_name' => 'James', 'last_name' => 'Brown', 'gender' => 'M', 'date_of_birth' => '1918-09-05', 'date_of_death' => '1990-07-20'],
        ['id' => 8, 'first_name' => 'Dorothy', 'last_name' => 'Brown', 'maiden_name' => 'Miller', 'gender' => 'F', 'date_of_birth' => '1922-12-18', 'date_of_death' => '1998-05-12'],
        
        // Generation 2 - Grandparents
        ['id' => 9, 'first_name' => 'Michael', 'last_name' => 'Anderson', 'gender' => 'M', 'date_of_birth' => '1948-04-22', 'is_living' => true, 'occupation' => 'Engineer'],
        ['id' => 10, 'first_name' => 'Patricia', 'last_name' => 'Anderson', 'maiden_name' => 'Brown', 'gender' => 'F', 'date_of_birth' => '1952-08-14', 'is_living' => true, 'occupation' => 'Teacher'],
        
        ['id' => 11, 'first_name' => 'David', 'last_name' => 'Johnson', 'gender' => 'M', 'date_of_birth' => '1945-11-30', 'is_living' => true, 'occupation' => 'Doctor'],
        ['id' => 12, 'first_name' => 'Susan', 'last_name' => 'Johnson', 'maiden_name' => 'Garcia', 'gender' => 'F', 'date_of_birth' => '1949-03-25', 'is_living' => true, 'occupation' => 'Nurse'],
        
        // Generation 3 - Parents
        ['id' => 13, 'first_name' => 'Christopher', 'last_name' => 'Anderson', 'gender' => 'M', 'date_of_birth' => '1975-02-18', 'is_living' => true, 'occupation' => 'Software Developer'],
        ['id' => 14, 'first_name' => 'Jennifer', 'last_name' => 'Anderson', 'maiden_name' => 'Johnson', 'gender' => 'F', 'date_of_birth' => '1978-09-10', 'is_living' => true, 'occupation' => 'Marketing Manager'],
        
        ['id' => 15, 'first_name' => 'Matthew', 'last_name' => 'Anderson', 'gender' => 'M', 'date_of_birth' => '1972-07-05', 'is_living' => true, 'occupation' => 'Architect'],
        ['id' => 16, 'first_name' => 'Lisa', 'last_name' => 'Anderson', 'maiden_name' => 'Taylor', 'gender' => 'F', 'date_of_birth' => '1974-12-22', 'is_living' => true, 'occupation' => 'Graphic Designer'],
        
        // Generation 4 - Children
        ['id' => 17, 'first_name' => 'Emily', 'last_name' => 'Anderson', 'gender' => 'F', 'date_of_birth' => '2005-06-15', 'is_living' => true],
        ['id' => 18, 'first_name' => 'Daniel', 'last_name' => 'Anderson', 'gender' => 'M', 'date_of_birth' => '2008-03-28', 'is_living' => true],
        ['id' => 19, 'first_name' => 'Sarah', 'last_name' => 'Anderson', 'gender' => 'F', 'date_of_birth' => '2010-11-12', 'is_living' => true],
        
        ['id' => 20, 'first_name' => 'Alexander', 'last_name' => 'Anderson', 'gender' => 'M', 'date_of_birth' => '2002-01-08', 'is_living' => true],
        ['id' => 21, 'first_name' => 'Olivia', 'last_name' => 'Anderson', 'gender' => 'F', 'date_of_birth' => '2004-09-20', 'is_living' => true],
        
        // Generation 5 - Grandchildren (for demonstration)
        ['id' => 22, 'first_name' => 'Ethan', 'last_name' => 'Anderson', 'gender' => 'M', 'date_of_birth' => '2025-05-10', 'is_living' => true],
        ['id' => 23, 'first_name' => 'Sophia', 'last_name' => 'Anderson', 'gender' => 'F', 'date_of_birth' => '2027-08-03', 'is_living' => true],
    ],
    'relationships' => [
        // Generation 0 marriages
        ['person1_id' => 1, 'person2_id' => 2, 'relationship_type' => 'spouse', 'relationship_subtype' => 'husband', 'marriage_date' => '1915-06-20'],
        ['person1_id' => 2, 'person2_id' => 1, 'relationship_type' => 'spouse', 'relationship_subtype' => 'wife', 'marriage_date' => '1915-06-20'],
        ['person1_id' => 3, 'person2_id' => 4, 'relationship_type' => 'spouse', 'relationship_subtype' => 'husband', 'marriage_date' => '1912-04-15'],
        ['person1_id' => 4, 'person2_id' => 3, 'relationship_type' => 'spouse', 'relationship_subtype' => 'wife', 'marriage_date' => '1912-04-15'],
        
        // Generation 0 to 1 (parent-child)
        ['person1_id' => 1, 'person2_id' => 5, 'relationship_type' => 'parent', 'relationship_subtype' => 'father'],
        ['person1_id' => 5, 'person2_id' => 1, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        ['person1_id' => 2, 'person2_id' => 5, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 5, 'person2_id' => 2, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        
        ['person1_id' => 3, 'person2_id' => 6, 'relationship_type' => 'parent', 'relationship_subtype' => 'father'],
        ['person1_id' => 6, 'person2_id' => 3, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
        ['person1_id' => 4, 'person2_id' => 6, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 6, 'person2_id' => 4, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
        
        // Generation 1 marriages
        ['person1_id' => 5, 'person2_id' => 6, 'relationship_type' => 'spouse', 'relationship_subtype' => 'husband', 'marriage_date' => '1945-08-12'],
        ['person1_id' => 6, 'person2_id' => 5, 'relationship_type' => 'spouse', 'relationship_subtype' => 'wife', 'marriage_date' => '1945-08-12'],
        ['person1_id' => 7, 'person2_id' => 8, 'relationship_type' => 'spouse', 'relationship_subtype' => 'husband', 'marriage_date' => '1940-10-05'],
        ['person1_id' => 8, 'person2_id' => 7, 'relationship_type' => 'spouse', 'relationship_subtype' => 'wife', 'marriage_date' => '1940-10-05'],
        
        // Generation 1 to 2 (parent-child)
        ['person1_id' => 5, 'person2_id' => 9, 'relationship_type' => 'parent', 'relationship_subtype' => 'father'],
        ['person1_id' => 9, 'person2_id' => 5, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        ['person1_id' => 6, 'person2_id' => 9, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 9, 'person2_id' => 6, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        
        ['person1_id' => 7, 'person2_id' => 10, 'relationship_type' => 'parent', 'relationship_subtype' => 'father'],
        ['person1_id' => 10, 'person2_id' => 7, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
        ['person1_id' => 8, 'person2_id' => 10, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 10, 'person2_id' => 8, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
        
        // Generation 2 marriages
        ['person1_id' => 9, 'person2_id' => 10, 'relationship_type' => 'spouse', 'relationship_subtype' => 'husband', 'marriage_date' => '1970-06-25'],
        ['person1_id' => 10, 'person2_id' => 9, 'relationship_type' => 'spouse', 'relationship_subtype' => 'wife', 'marriage_date' => '1970-06-25'],
        ['person1_id' => 11, 'person2_id' => 12, 'relationship_type' => 'spouse', 'relationship_subtype' => 'husband', 'marriage_date' => '1968-04-18'],
        ['person1_id' => 12, 'person2_id' => 11, 'relationship_type' => 'spouse', 'relationship_subtype' => 'wife', 'marriage_date' => '1968-04-18'],
        
        // Generation 2 to 3 (parent-child)
        ['person1_id' => 9, 'person2_id' => 13, 'relationship_type' => 'parent', 'relationship_subtype' => 'father'],
        ['person1_id' => 13, 'person2_id' => 9, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        ['person1_id' => 10, 'person2_id' => 13, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 13, 'person2_id' => 10, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        
        ['person1_id' => 9, 'person2_id' => 15, 'relationship_type' => 'parent', 'relationship_subtype' => 'father'],
        ['person1_id' => 15, 'person2_id' => 9, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        ['person1_id' => 10, 'person2_id' => 15, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 15, 'person2_id' => 10, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        
        ['person1_id' => 11, 'person2_id' => 14, 'relationship_type' => 'parent', 'relationship_subtype' => 'father'],
        ['person1_id' => 14, 'person2_id' => 11, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
        ['person1_id' => 12, 'person2_id' => 14, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 14, 'person2_id' => 12, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
        
        // Generation 3 marriages
        ['person1_id' => 13, 'person2_id' => 14, 'relationship_type' => 'spouse', 'relationship_subtype' => 'husband', 'marriage_date' => '2000-05-20'],
        ['person1_id' => 14, 'person2_id' => 13, 'relationship_type' => 'spouse', 'relationship_subtype' => 'wife', 'marriage_date' => '2000-05-20'],
        ['person1_id' => 15, 'person2_id' => 16, 'relationship_type' => 'spouse', 'relationship_subtype' => 'husband', 'marriage_date' => '1998-09-12'],
        ['person1_id' => 16, 'person2_id' => 15, 'relationship_type' => 'spouse', 'relationship_subtype' => 'wife', 'marriage_date' => '1998-09-12'],
        
        // Generation 3 to 4 (parent-child)
        ['person1_id' => 13, 'person2_id' => 17, 'relationship_type' => 'parent', 'relationship_subtype' => 'father'],
        ['person1_id' => 17, 'person2_id' => 13, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
        ['person1_id' => 14, 'person2_id' => 17, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 17, 'person2_id' => 14, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
        
        ['person1_id' => 13, 'person2_id' => 18, 'relationship_type' => 'parent', 'relationship_subtype' => 'father'],
        ['person1_id' => 18, 'person2_id' => 13, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        ['person1_id' => 14, 'person2_id' => 18, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 18, 'person2_id' => 14, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        
        ['person1_id' => 13, 'person2_id' => 19, 'relationship_type' => 'parent', 'relationship_subtype' => 'father'],
        ['person1_id' => 19, 'person2_id' => 13, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
        ['person1_id' => 14, 'person2_id' => 19, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 19, 'person2_id' => 14, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
        
        ['person1_id' => 15, 'person2_id' => 20, 'relationship_type' => 'parent', 'relationship_subtype' => 'father'],
        ['person1_id' => 20, 'person2_id' => 15, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        ['person1_id' => 16, 'person2_id' => 20, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 20, 'person2_id' => 16, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        
        ['person1_id' => 15, 'person2_id' => 21, 'relationship_type' => 'parent', 'relationship_subtype' => 'father'],
        ['person1_id' => 21, 'person2_id' => 15, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
        ['person1_id' => 16, 'person2_id' => 21, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 21, 'person2_id' => 16, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
        
        // Generation 4 to 5 (future children for demonstration)
        ['person1_id' => 17, 'person2_id' => 22, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 22, 'person2_id' => 17, 'relationship_type' => 'child', 'relationship_subtype' => 'son'],
        ['person1_id' => 17, 'person2_id' => 23, 'relationship_type' => 'parent', 'relationship_subtype' => 'mother'],
        ['person1_id' => 23, 'person2_id' => 17, 'relationship_type' => 'child', 'relationship_subtype' => 'daughter'],
    ]
];

// Get tree statistics
$stats = generateTreeStatistics($sample_family_data);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="text-center my-4 text-white">
                <i class="fas fa-tree"></i> Dynamic Family Tree Demo
            </h1>
            <p class="text-center text-light mb-4">
                Showcasing unlimited generation support with 6 generations of the Anderson family
            </p>
        </div>
    </div>
    
    <!-- Enhanced Statistics Panel -->
    <div class="stats-panel text-center mb-4">
        <div class="row">
            <div class="col-md-2">
                <h4><?php echo $stats['total_members']; ?></h4>
                <small>Total Members</small>
            </div>
            <div class="col-md-2">
                <h4><?php echo $stats['males']; ?></h4>
                <small>Males</small>
            </div>
            <div class="col-md-2">
                <h4><?php echo $stats['females']; ?></h4>
                <small>Females</small>
            </div>
            <div class="col-md-2">
                <h4><?php echo $stats['living_members']; ?></h4>
                <small>Living</small>
            </div>
            <div class="col-md-2">
                <h4><?php echo $stats['deceased_members']; ?></h4>
                <small>Deceased</small>
            </div>
            <div class="col-md-2">
                <h4 id="generation-count">6</h4>
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
    
    <!-- Dynamic Family Tree Visualization -->
    <div class="tree-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="fas fa-sitemap"></i> Anderson Family Tree (6 Generations)</h3>
            <div class="text-muted">
                <small>
                    <i class="fas fa-info-circle"></i> 
                    Use Ctrl+Scroll to zoom, drag to pan, click members for details
                </small>
            </div>
        </div>
        
        <div class="family-tree">
            <?php echo renderFamilyTree($sample_family_data); ?>
        </div>
    </div>
    
    <!-- Feature Highlights -->
    <div class="row mt-5">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-expand-arrows-alt fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Unlimited Generations</h5>
                    <p class="card-text">
                        Supports any number of generations with automatic layout and spacing. 
                        This demo shows 6 generations from great-great-grandparents to great-grandchildren.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-search-plus fa-3x text-success mb-3"></i>
                    <h5 class="card-title">Interactive Navigation</h5>
                    <p class="card-text">
                        Zoom, pan, search, and navigate through the tree with smooth animations. 
                        Fullscreen mode available for large family trees.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-mobile-alt fa-3x text-info mb-3"></i>
                    <h5 class="card-title">Responsive Design</h5>
                    <p class="card-text">
                        Works perfectly on desktop, tablet, and mobile devices with touch-friendly controls 
                        and optimized layouts for all screen sizes.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Technical Features -->
    <div class="row mt-4 mb-5">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cogs"></i> Technical Features Demonstrated</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">✅ Dynamic generation calculation</li>
                                <li class="list-group-item">✅ Automatic spouse grouping</li>
                                <li class="list-group-item">✅ Smart connection line rendering</li>
                                <li class="list-group-item">✅ Generation-aware layout engine</li>
                                <li class="list-group-item">✅ Multiple marriage support</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">✅ Zoom and pan functionality</li>
                                <li class="list-group-item">✅ Member search and highlighting</li>
                                <li class="list-group-item">✅ Fullscreen tree viewing</li>
                                <li class="list-group-item">✅ Touch-friendly mobile interface</li>
                                <li class="list-group-item">✅ Keyboard navigation shortcuts</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Additional JavaScript for demo features -->
<script>
// Demo-specific functions
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

function clearSearch() {
    const searchInput = document.getElementById('member-search');
    if (searchInput) {
        searchInput.value = '';
        document.querySelectorAll('.member-card.search-highlight').forEach(card => {
            card.classList.remove('search-highlight');
        });
    }
}

// Initialize demo features
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced search functionality
    const searchInput = document.getElementById('member-search');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const searchTerm = e.target.value.trim();
                
                document.querySelectorAll('.member-card.search-highlight').forEach(card => {
                    card.classList.remove('search-highlight');
                });
                
                if (searchTerm.length > 1) {
                    const matches = window.FamilyTreeNav ? window.FamilyTreeNav.findMemberByName(searchTerm) : [];
                    matches.forEach(card => {
                        card.classList.add('search-highlight');
                    });
                    
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
    
    // Auto-fit the tree after a short delay
    setTimeout(() => {
        if (window.FamilyTreeNav) {
            window.FamilyTreeNav.autoFitTree();
        }
    }, 1000);
});
</script>

<style>
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

.card {
    border: none;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border-radius: 10px;
}

.card-header {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border-radius: 10px 10px 0 0 !important;
}

.list-group-item {
    border: none;
    padding: 0.5rem 0;
}
</style>

<!-- Add family tree specific JavaScript -->
<script src="assets/js/family-tree.js"></script>

<?php require_once 'templates/footer.php'; ?>
