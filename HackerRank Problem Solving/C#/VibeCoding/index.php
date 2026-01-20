<?php
session_start(); // Technically not needed for pure JWT, but good practice for session-based flags.

// JWT check would happen here or in an included file.
// For now, we simulate the check. A real implementation would verify the token.
$is_logged_in = false; // This will be updated by JS based on localStorage token

// The code below is a placeholder for where you would put the real server-side check
// if you were doing a server-side render. In our SPA-like model, JS handles the redirect.

$projectName = getenv('PROJECT_NAME') ?: 'StudyMate AI';
$projectDescription = getenv('PROJECT_DESCRIPTION') ?: 'An AI-powered study helper to summarize notes, explain concepts, and generate flashcards.';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($projectName); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($projectDescription); ?>">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo time(); ?>">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="bi bi-brain"></i> <?php echo htmlspecialchars($projectName); ?></a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item" id="logout-button-container" style="display: none;">
                    <button class="btn btn-secondary" id="logout-button">Logout</button>
                </li>
            </ul>
        </div>
    </div>
</nav>

    <main class="container py-5">
        <section class="hero-section text-center mb-5">
            <h1 class="display-3 fw-bold">Unlock Your Study Potential</h1>
            <p class="lead text-muted col-lg-8 mx-auto">Paste any text—from lecture notes to dense articles—and let our AI assistant summarize key points, explain complex topics in simple terms, or generate flashcards to supercharge your learning.</p>
        </section>

        <section class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="ai-widget card shadow-sm border-0">
                    <div class="card-body p-4">
                        <form id="ai-form">
                            <div class="mb-3">
                                <label for="input-text" class="form-label fs-5">Your Study Material</label>
                                <textarea class="form-control" id="input-text" rows="8" placeholder="Paste your notes, an article, or a complex paragraph here..."></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fs-5">What should the AI do?</label>
                                <div id="action-buttons" class="btn-group w-100" role="group">
                                    <button type="button" class="btn btn-outline-primary active" data-action="summarize"><i class="bi bi-card-text me-2"></i>Summarize</button>
                                    <button type="button" class="btn btn-outline-primary" data-action="explain"><i class="bi bi-lightbulb me-2"></i>Explain Simply</button>
                                    <button type="button" class="btn btn-outline-primary" data-action="flashcards"><i class="bi bi-layers me-2"></i>Create Flashcards</button>
                                </div>
                                <input type="hidden" id="selected-action" value="summarize">
                            </div>

                            <button type="submit" id="submit-button" class="btn btn-primary btn-lg w-100" disabled><i class="bi bi-magic"></i> Generate Response</button>
                        </form>

                        <div id="ai-result-container" class="mt-4" style="display: none;">
                            <h3 class="h4">Result</h3>
                            <div id="spinner" class="d-flex justify-content-center align-items-center mt-4" style="display: none;">
                                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                            <div id="api-error" class="alert alert-danger mt-3" style="display: none;"></div>
                            <div id="ai-response-area" class="mt-3 p-3 bg-light rounded" style="display: none;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row justify-content-center mt-5 pt-3">
            <div class="col-lg-8 col-xl-7">
                <h2 class="h3 mb-3 text-center">Your Study History</h2>
                <div class="study-history">
                    <div class="accordion" id="study-history-accordion">
                        <div id="history-loading" class="text-center p-4 text-muted">
                            <div class="spinner-border spinner-border-sm text-secondary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <span class="ms-2">Loading history...</span>
                        </div>
                        <div id="history-empty" class="text-center p-4 text-muted" style="display: none;">
                            <i class="bi bi-cloud-drizzle fs-2 d-block mb-2"></i>
                            Your study history is empty. <br>
                            Generate a response above to start building your collection.
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer mt-5">
        <div class="container text-center">
            <span class="text-muted">© <?php echo date("Y"); ?> <?php echo htmlspecialchars($projectName); ?>. All Rights Reserved.</span>
        </div>
    </footer>

    <!-- History Detail Modal -->
    <div class="modal fade" id="history-detail-modal" tabindex="-1" aria-labelledby="history-detail-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="history-detail-modal-label">Session Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="history-detail-modal-body">
                    <!-- Content will be injected here -->
                </div>
            </div>
        </div>
    </div>


    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
</body>
</html>