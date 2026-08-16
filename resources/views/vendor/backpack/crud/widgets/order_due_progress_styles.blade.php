{{-- Compact days-left tags; urgent red bar + tag blink strongly in sync. --}}
<style>
.order-due-days-tag {
    font-size: 0.65rem;
    font-weight: 600;
    line-height: 1.35;
    padding: 0.25em 0.7em;
    white-space: nowrap;
    overflow: visible;
    display: inline-block;
    min-width: 3.25rem;
    text-align: center;
}

.progress .progress-bar.order-due-urgent-bar {
    transition: none !important;
    animation: order-due-urgent-bar-pulse 0.9s ease-in-out infinite;
}

.order-due-urgent-label {
    transition: none !important;
    animation: order-due-urgent-tag-pulse 0.9s ease-in-out infinite;
}

@keyframes order-due-urgent-bar-pulse {
    0%,
    100% {
        opacity: 1;
        box-shadow: 0 0 0 0 rgba(214, 57, 57, 0.8);
    }
    50% {
        opacity: 0.15;
        box-shadow: 0 0 8px 2px rgba(214, 57, 57, 0.55);
    }
}

@keyframes order-due-urgent-tag-pulse {
    0%,
    100% {
        opacity: 1;
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(214, 57, 57, 0.7);
        filter: brightness(1);
    }
    50% {
        opacity: 0.25;
        transform: scale(1.08);
        box-shadow: 0 0 0 4px rgba(214, 57, 57, 0.35);
        filter: brightness(1.25);
    }
}
</style>
