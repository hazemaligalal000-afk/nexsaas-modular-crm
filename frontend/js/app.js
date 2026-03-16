document.addEventListener('DOMContentLoaded', () => {
    // Basic setup for SaaS Kanban Board
    const API_URL = 'http://localhost:8080/api';
    const API_KEY = 'YOUR_ORG_API_KEY_HERE'; // In real app, injected via session
    
    const stages = [
        { id: 'New Lead', name: 'New Lead', class: 'stage-new' },
        { id: 'Qualified', name: 'Qualified', class: 'stage-qualified' },
        { id: 'Demo', name: 'Demo', class: 'stage-demo' },
        { id: 'Proposal', name: 'Proposal', class: 'stage-proposal' },
        { id: 'Negotiation', name: 'Negotiation', class: 'stage-negotiation' },
        { id: 'Closed Won', name: 'Closed Won', class: 'stage-won' },
        { id: 'Closed Lost', name: 'Closed Lost', class: 'stage-lost' }
    ];

    // Mock initial Deals representing our Multi-company tenant logic fetch
    let deals = [
        { id: '1', title: 'Enterprise SLA Renewal', company: 'Acme Corp', value: 125000, date: 'Oct 15, 2026', stage: 'Proposal' },
        { id: '2', title: 'Q4 Implementation', company: 'TechNova', value: 85000, date: 'Nov 01, 2026', stage: 'Negotiation' },
        { id: '3', title: 'Cloud Migration', company: 'Starlight Inc', value: 45000, date: 'Oct 20, 2026', stage: 'Qualified' },
        { id: '4', title: 'API Integration', company: 'DevBox', value: 25000, date: 'Dec 15, 2026', stage: 'New Lead' },
        { id: '5', title: 'Global VPN Setup', company: 'SecureNet', value: 172000, date: 'Sep 30, 2026', stage: 'Closed Won' }
    ];

    const kanbanBoard = document.getElementById('kanbanBoard');
    const template = document.getElementById('dealCardTemplate');

    function renderBoard() {
        kanbanBoard.innerHTML = '';
        
        stages.forEach(stage => {
            // Create Column
            const col = document.createElement('div');
            col.className = 'kanban-column ' + stage.class;
            col.dataset.stage = stage.id;
            
            // Filter deals for this stage
            const stageDeals = deals.filter(d => d.stage === stage.id);
            
            col.innerHTML = `
                <div class="column-header">
                    <div class="column-title"><div class="stage-dot"></div>${stage.name}</div>
                    <div class="column-count">${stageDeals.length}</div>
                </div>
                <div class="kanban-cards-container" id="stage-${stage.id.replace(' ', '')}"></div>
            `;
            
            kanbanBoard.appendChild(col);
            
            // Add Cards
            const container = col.querySelector('.kanban-cards-container');
            stageDeals.forEach(deal => {
                const clone = template.content.cloneNode(true);
                const card = clone.querySelector('.kanban-card');
                
                card.dataset.id = deal.id;
                clone.querySelector('.deal-title').textContent = deal.title;
                clone.querySelector('.company-name').textContent = deal.company;
                clone.querySelector('.deal-value').textContent = '$' + deal.value.toLocaleString();
                clone.querySelector('.deal-date').textContent = deal.date;
                
                container.appendChild(clone);
            });

            // Make Sortable
            new Sortable(container, {
                group: 'kanban',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function (evt) {
                    const itemEl = evt.item;
                    const dealId = itemEl.dataset.id;
                    const newStage = evt.to.closest('.kanban-column').dataset.stage;
                    
                    // Update Deals Array
                    const deal = deals.find(d => d.id === dealId);
                    if (deal && deal.stage !== newStage) {
                        const oldStage = deal.stage;
                        deal.stage = newStage;
                        
                        console.log(`Moved Deal ${dealId} from ${oldStage} to ${newStage}`);
                        
                        // Fire off API Request to update Sales Pipeline
                        updateDealAPI(dealId, newStage);
                        
                        // Optimistically re-render to update counts, but we can also just update DOM directly.
                        // renderBoard();
                        updateCounts();
                    }
                }
            });
        });
    }

    function updateCounts() {
        document.querySelectorAll('.kanban-column').forEach(col => {
            const count = col.querySelectorAll('.kanban-card').length;
            col.querySelector('.column-count').textContent = count;
        });
    }

    function updateDealAPI(dealId, newStage) {
        // In reality, this communicates with the DealsController PHP script:
        // fetch(`${API_URL}/deals/${dealId}`, {
        //     method: 'PUT',
        //     headers: { 'Content-Type': 'application/json', 'X-API-Key': API_KEY },
        //     body: JSON.stringify({ sales_stage: newStage })
        // })
        // .then(res => res.json())
        // .then(data => console.log(data));
        
        console.log(`[API MOCK] Updated Deal ID ${dealId} to Stage: ${newStage}`);
    }

    // Stripe billing connect mock
    document.getElementById('btnConnectStripe').addEventListener('click', () => {
        alert("Redirecting to Stripe Custom Connect flow for Tenant Subscriptions...");
    });

    // Initialize UI
    renderBoard();
});
