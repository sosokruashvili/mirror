{{-- Compact days-left tags; the urgent red tag and dot blink in sync. --}}
<style>
.order-due-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.order-due-dot {
    display: inline-block;
    flex: 0 0 auto;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    vertical-align: middle;
}

.order-due-dot-success {
    background-color: #2fb344;
    box-shadow: 0 0 0 2px rgba(47, 179, 68, 0.25);
}

.order-due-dot-warning {
    background-color: #f59f00;
    box-shadow: 0 0 0 2px rgba(245, 159, 0, 0.25);
}

.order-due-dot-danger {
    background-color: #d63939;
    box-shadow: 0 0 0 2px rgba(214, 57, 57, 0.3);
}

.order-due-dot-urgent {
    transition: none !important;
    animation: order-due-urgent-dot-pulse 0.9s ease-in-out infinite;
}

@keyframes order-due-urgent-dot-pulse {
    0%,
    100% {
        opacity: 1;
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(214, 57, 57, 0.7);
    }
    50% {
        opacity: 0.25;
        transform: scale(1.25);
        box-shadow: 0 0 0 4px rgba(214, 57, 57, 0.35);
    }
}

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

.order-due-urgent-label {
    transition: none !important;
    animation: order-due-urgent-tag-pulse 0.9s ease-in-out infinite;
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
