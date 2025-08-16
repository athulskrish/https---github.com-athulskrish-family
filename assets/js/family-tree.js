// Initialize tree zoom level
let currentZoom = 1;

// Function to zoom the family tree
function zoomTree(factor) {
    currentZoom *= factor;
    // Limit zoom range
    currentZoom = Math.min(Math.max(0.5, currentZoom), 2);
    
    const tree = document.querySelector('.family-tree');
    if (tree) {
        tree.style.transform = `scale(${currentZoom})`;
    }
}

// Add mouse wheel zoom support
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.family-tree-container');
    if (container) {
        container.addEventListener('wheel', function(e) {
            if (e.ctrlKey) {
                e.preventDefault();
                const zoomFactor = e.deltaY > 0 ? 0.9 : 1.1;
                zoomTree(zoomFactor);
            }
        });
    }
});
