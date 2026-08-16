{{-- Inline so due-date blink styles are never stuck behind a stale Basset CSS copy. --}}
<style>
.progress .progress-bar.order-due-urgent-bar {
    transition: none !important;
    animation: order-due-urgent-bar-pulse 1.2s ease-in-out infinite !important;
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
