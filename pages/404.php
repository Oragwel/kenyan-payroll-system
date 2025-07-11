<?php
/**
 * 404 Error Page
 */
?>

<div class="container-fluid">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-6 text-center">
            <div class="error-page">
                <h1 class="display-1 text-muted">404</h1>
                <h2 class="mb-4">Page Not Found</h2>
                <p class="lead text-muted mb-4">
                    The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.
                </p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="index.php?page=dashboard" class="btn btn-primary">
                        <i class="fas fa-home"></i> Go to Dashboard
                    </a>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.error-page h1 {
    font-size: 8rem;
    font-weight: 300;
}

@media (max-width: 768px) {
    .error-page h1 {
        font-size: 6rem;
    }
}
</style>
