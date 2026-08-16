{{-- Inline so due-date blink styles are never stuck behind a stale Basset CSS copy. --}}
<style>
.progress .progress-bar.order-due-urgent-bar {
    transition: none !important;
    animation: order-due-urgent-bar-pulse 1.2s ease-in-out infinite !important;
}

.order-due-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.order-due-dot-success {
    background-color: var(--bs-success, #198754);
}

.order-due-dot-warning {
    background-color: var(--bs-warning, #ffc107);
}

.order-due-dot-danger {
    background-color: var(--bs-danger, #dc3545);
}

/* Shared urgent red: glow ripple + fade. Reuse .urgent-red-pulse anywhere. */
.urgent-red-pulse,
.order-due-dot-urgent {
    box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
    animation: urgent-red-pulse 1.4s ease-in-out infinite !important;
}

@keyframes urgent-red-pulse {
    0%,
    100% {
        opacity: 1;
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        transform: scale(1);
    }
    50% {
        opacity: 0.2;
        box-shadow: 0 0 0 6px rgba(220, 53, 69, 0);
        transform: scale(1.08);
    }
}

@keyframes order-due-urgent-bar-pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.15;
    }
}
</style>
