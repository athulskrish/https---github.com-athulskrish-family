function initializeFamilyTree(members) {
    const container = document.getElementById('family-tree-container');
    let scale = 1;
    
    function renderMemberCard(member) {
        const card = document.createElement('div');
        card.className = `member-card ${member.gender.toLowerCase()}`;
        
        const name = document.createElement('div');
        name.className = 'member-name';
        name.textContent = `${member.first_name} ${member.last_name}`;
        
        const dates = document.createElement('div');
        dates.className = 'member-dates';
        const birthYear = member.date_of_birth ? new Date(member.date_of_birth).getFullYear() : '?';
        dates.textContent = birthYear;
        
        card.appendChild(name);
        card.appendChild(dates);
        
        // Add click handler to show details
        card.addEventListener('click', () => showMemberDetails(member));
        
        return card;
    }
    
    function renderTree() {
        // Clear previous content
        container.innerHTML = '';
        
        // Group members by generation (using birth dates as a simple heuristic)
        const generations = groupByGenerations(members);
        
        // Create and append generation containers
        generations.forEach((genMembers, genIndex) => {
            const genContainer = document.createElement('div');
            genContainer.className = 'generation';
            
            const title = document.createElement('h5');
            title.className = 'generation-title w-100 text-center mb-3';
            title.textContent = `Generation ${genIndex + 1}`;
            genContainer.appendChild(title);
            
            // Group siblings together
            const siblingGroups = groupSiblings(genMembers);
            
            siblingGroups.forEach(group => {
                if (group.length > 1) {
                    const siblingGroup = document.createElement('div');
                    siblingGroup.className = 'siblings-group';
                    group.forEach(member => {
                        siblingGroup.appendChild(renderMemberCard(member));
                    });
                    genContainer.appendChild(siblingGroup);
                } else {
                    genContainer.appendChild(renderMemberCard(group[0]));
                }
            });
            
            container.appendChild(genContainer);
        });
        
        // Apply scale transform
        container.style.transform = `scale(${scale})`;
    }
    
    function groupByGenerations(members) {
        // Simple grouping based on birth date decades
        const generations = [];
        const membersByDecade = {};
        
        members.forEach(member => {
            if (member.date_of_birth) {
                const year = new Date(member.date_of_birth).getFullYear();
                const decade = Math.floor(year / 10) * 10;
                membersByDecade[decade] = membersByDecade[decade] || [];
                membersByDecade[decade].push(member);
            } else {
                // Put members without birth dates in generation 0
                membersByDecade[0] = membersByDecade[0] || [];
                membersByDecade[0].push(member);
            }
        });
        
        Object.keys(membersByDecade)
            .sort((a, b) => a - b)
            .forEach(decade => {
                generations.push(membersByDecade[decade]);
            });
        
        return generations;
    }
    
    function groupSiblings(members) {
        // Group members with same parents together
        const groups = [];
        const processed = new Set();
        
        members.forEach(member => {
            if (processed.has(member.id)) return;
            
            const group = [member];
            processed.add(member.id);
            
            // Find siblings (members with same parent_ids)
            if (member.parent_ids) {
                const memberParents = new Set(member.parent_ids.split(','));
                members.forEach(otherMember => {
                    if (
                        otherMember.id !== member.id &&
                        !processed.has(otherMember.id) &&
                        otherMember.parent_ids
                    ) {
                        const otherParents = new Set(otherMember.parent_ids.split(','));
                        // Check if they share at least one parent
                        const hasCommonParent = [...memberParents].some(p => otherParents.has(p));
                        if (hasCommonParent) {
                            group.push(otherMember);
                            processed.add(otherMember.id);
                        }
                    }
                });
            }
            
            groups.push(group);
        });
        
        return groups;
    }
    
    function showMemberDetails(member) {
        // Implement member details modal/popup here
        console.log('Show details for:', member);
    }
    
    // Initialize zoom controls
    window.zoomIn = function() {
        scale = Math.min(scale * 1.2, 2);
        container.style.transform = `scale(${scale})`;
    };
    
    window.zoomOut = function() {
        scale = Math.max(scale / 1.2, 0.5);
        container.style.transform = `scale(${scale})`;
    };
    
    window.resetZoom = function() {
        scale = 1;
        container.style.transform = `scale(${scale})`;
    };
    
    // Initial render
    renderTree();
}
