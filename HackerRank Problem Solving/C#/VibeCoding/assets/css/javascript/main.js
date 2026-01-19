document.addEventListener('DOMContentLoaded', function () {
    const token = localStorage.getItem('token');
    if (!token) {
        window.location.href = 'login.php';
        return;
    }

    // --- DOM Elements ---
    const logoutButton = document.getElementById('logout-button');
    const aiForm = document.getElementById('ai-form');
    const inputText = document.getElementById('input-text');
    const submitButton = document.getElementById('submit-button');
    const actionButtons = document.getElementById('action-buttons');
    const selectedActionInput = document.getElementById('selected-action');
    
    const resultContainer = document.getElementById('ai-result-container');
    const responseArea = document.getElementById('ai-response-area');
    const spinner = document.getElementById('spinner');
    const apiError = document.getElementById('api-error');

    const historyAccordion = document.getElementById('study-history-accordion');
    const historyLoading = document.getElementById('history-loading');
    const historyEmpty = document.getElementById('history-empty');
    
    const historyModal = new bootstrap.Modal(document.getElementById('history-detail-modal'));
    const historyModalBody = document.getElementById('history-detail-modal-body');

    // --- EVENT LISTENERS ---

    // Handle Logout
    logoutButton?.addEventListener('click', () => {
        localStorage.removeItem('token');
        window.location.href = 'login.php';
    });
    
    // Enable/disable submit button based on input
    inputText?.addEventListener('input', () => {
        if (submitButton) submitButton.disabled = !inputText.value.trim();
    });

    // Handle action button clicks
    actionButtons?.addEventListener('click', function(e) {
        if (e.target.tagName === 'BUTTON') {
            actionButtons.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
            e.target.classList.add('active');
            if (selectedActionInput) selectedActionInput.value = e.target.dataset.action;
        }
    });

    // Handle form submission
    aiForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = inputText.value;
        const action = selectedActionInput.value;

        if (!text.trim()) return;

        showLoading();

        authFetch('api.php', {
            method: 'POST',
            body: JSON.stringify({ text, action })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showResult(data.data, action);
                loadStudyHistory(); // Refresh history
            } else {
                showError(data.error || 'An unknown error occurred.');
            }
        })
        .catch(error => {
            showError('Failed to connect to the server. Please check your connection and try again.');
            console.error('Fetch Error:', error);
        });
    });

    // Handle clicks on "View Full Response" in history
    historyAccordion?.addEventListener('click', function(e) {
        const viewButton = e.target.closest('.view-details-btn');
        if (viewButton) {
            const sessionId = viewButton.dataset.sessionId;
            renderHistoryDetail(sessionId);
        }
    });

    // --- UI Update Functions ---

    function showLoading() {
        if (!resultContainer || !spinner || !apiError || !responseArea) return;
        resultContainer.style.display = 'block';
        spinner.style.display = 'flex';
        apiError.style.display = 'none';
        responseArea.style.display = 'none';
        responseArea.innerHTML = '';
    }

    function showResult(data, action) {
        if (!spinner || !responseArea) return;
        spinner.style.display = 'none';
        responseArea.style.display = 'block';
        if (action === 'flashcards') {
            renderFlashcards(data, responseArea);
        } else {
            renderTextResponse(data, responseArea);
        }
    }

    function showError(message) {
        if (!spinner || !apiError) return;
        spinner.style.display = 'none';
        apiError.textContent = message;
        apiError.style.display = 'block';
    }

    function updateHistoryUI(state, data = []) {
        if (!historyLoading || !historyEmpty || !historyAccordion) return;
        
        historyLoading.style.display = (state === 'loading') ? 'block' : 'none';
        historyEmpty.style.display = (state === 'empty') ? 'block' : 'none';
        
        historyAccordion.innerHTML = ''; // Clear previous items

        if (state === 'loaded') {
            data.forEach(session => {
                const actionIcon = getActionIcon(session.action_type);
                const truncatedText = session.input_text.substring(0, 80) + (session.input_text.length > 80 ? '...' : '');
                
                const accordionItem = document.createElement('div');
                accordionItem.className = 'accordion-item';
                accordionItem.innerHTML = `
                    <h2 class="accordion-header" id="heading-${session.id}">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-${session.id}" aria-expanded="false" aria-controls="collapse-${session.id}">
                            <i class="bi ${actionIcon} me-2"></i>
                            <span class="fw-bold me-2">${session.action_type.charAt(0).toUpperCase() + session.action_type.slice(1)}:</span>
                            <span class="text-muted fst-italic me-3">"${escapeHTML(truncatedText)}"</span>
                            <span class="ms-auto text-muted small">${new Date(session.created_at).toLocaleString()}</span>
                        </button>
                    </h2>
                    <div id="collapse-${session.id}" class="accordion-collapse collapse" aria-labelledby="heading-${session.id}">
                        <div class="accordion-body text-end">
                             <button class="btn btn-sm btn-outline-primary view-details-btn" data-session-id="${session.id}">
                                <i class="bi bi-box-arrow-up-right me-1"></i> View Details
                            </button>
                        </div>
                    </div>
                `;
                historyAccordion.appendChild(accordionItem);
            });
        }
    }

    // --- DATA FETCHING & RENDERING ---

    const authFetch = (url, options = {}) => {
        const headers = {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            ...options.headers,
        };

        return fetch(url, { ...options, headers }).then(response => {
            if (response.status === 401) {
                localStorage.removeItem('token');
                window.location.href = 'login.php';
                throw new Error('Unauthorized');
            }
            return response;
        });
    };

    function loadStudyHistory() {
        updateHistoryUI('loading');
        authFetch('api.php?action=history')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    updateHistoryUI('loaded', data.data);
                } else if (data.success) {
                    updateHistoryUI('empty');
                } else {
                    historyAccordion.innerHTML = `<div class="text-center p-3 text-danger">${data.error || 'Could not load history.'}</div>`;
                }
            })
            .catch(error => {
                console.error('History Error:', error);
                if (historyAccordion) historyAccordion.innerHTML = '<div class="text-center p-3 text-danger">Error loading history. Please refresh the page.</div>';
            });
    }

    function renderHistoryDetail(sessionId) {
        historyModalBody.innerHTML = '<div class="d-flex justify-content-center align-items-center mt-3"><div class="spinner-border text-primary"></div></div>';
        historyModal.show();

        authFetch(`api.php?action=flashcards&session_id=${sessionId}`)
            .then(response => response.json())
            .then(data => {
                historyModalBody.innerHTML = '';
                if (data.success) {
                    // The actual content (text or flashcards) is in data.data.ai_response
                    const responseContent = JSON.parse(data.data.ai_response);
                    
                    if (data.data.action_type === 'flashcards') {
                         renderFlashcards(responseContent.data, historyModalBody);
                    } else {
                         renderTextResponse(responseContent.data, historyModalBody);
                    }
                } else {
                     historyModalBody.innerHTML = `<p class="text-danger">${data.error || 'Could not load session details.'}</p>`;
                }
            })
            .catch(error => {
                historyModalBody.innerHTML = '<p class="text-danger">Error fetching session details.</p>';
                console.error('Detail Error:', error);
            });
    }

    // --- RENDER HELPERS ---

    function renderTextResponse(text, container) {
        const p = document.createElement('p');
        p.innerHTML = escapeHTML(text).replace(/\n/g, '<br>');
        container.appendChild(p);
    }

    function renderFlashcards(flashcards, container) {
        if (!Array.isArray(flashcards) || flashcards.length === 0) {
            container.innerHTML = '<p>No flashcards were generated for this session.</p>';
            return;
        }

        const flashcardContainer = document.createElement('div');
        flashcardContainer.className = 'row';

        flashcards.forEach(card => {
            const col = document.createElement('div');
            col.className = 'col-md-6 mb-3';
            
            const cardEl = document.createElement('div');
            cardEl.className = 'flashcard';
            cardEl.innerHTML = `
                <div class="flashcard-inner">
                    <div class="flashcard-front"><p>${escapeHTML(card.question)}</p></div>
                    <div class="flashcard-back"><p>${escapeHTML(card.answer)}</p></div>
                </div>
            `;
            cardEl.addEventListener('click', () => cardEl.classList.toggle('is-flipped'));
            col.appendChild(cardEl);
            flashcardContainer.appendChild(col);
        });
        container.appendChild(flashcardContainer);
    }
    
    function getActionIcon(action) {
        switch(action) {
            case 'summarize': return 'bi-card-text';
            case 'explain': return 'bi-lightbulb';
            case 'flashcards': return 'bi-layers';
            default: return 'bi-question-circle';
        }
    }

    function escapeHTML(str) {
        if (typeof str !== 'string') return '';
        const p = document.createElement('p');
        p.textContent = str;
        return p.innerHTML;
    }

    // --- Initial Load ---
    document.getElementById('logout-button-container').style.display = 'block';
    loadStudyHistory();
});