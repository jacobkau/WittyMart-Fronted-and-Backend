/* ===== ADMIN FOOTER ===== */
.admin-footer {
    background: var(--white);
    border-top: 1px solid var(--border);
    padding: 15px 25px;
    margin-top: 30px;
    box-shadow: 0 -2px 10px var(--shadow-color);
    position: sticky;
    bottom: 0;
    z-index: 99;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

body.dark-mode .admin-footer {
    background: var(--card-bg);
    border-top-color: var(--border-color);
}

.footer-content {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.footer-left p,
.footer-center p,
.footer-right p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-muted);
}

.footer-left strong {
    color: var(--primary-color);
}

.footer-center a {
    color: var(--primary-color);
    text-decoration: none;
    transition: all 0.3s ease;
}

.footer-center a:hover {
    text-decoration: underline;
}

.footer-center i.fa-heart {
    animation: heartBeat 1.5s ease-in-out infinite;
}

@keyframes heartBeat {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

.footer-divider {
    margin: 0 10px;
    color: var(--border-color);
}

.footer-right i.fa-clock {
    color: var(--primary-color);
    margin-right: 5px;
}

/* Footer Responsive */
@media (max-width: 768px) {
    .footer-content {
        flex-direction: column;
        text-align: center;
        gap: 5px;
    }
    
    .footer-left p,
    .footer-center p,
    .footer-right p {
        font-size: 0.75rem;
    }
    
    .footer-divider {
        display: none;
    }
    
    .admin-footer {
        padding: 12px 15px;
        position: relative;
    }
}

@media (max-width: 480px) {
    .admin-footer {
        padding: 10px 12px;
    }
    
    .footer-left p,
    .footer-center p,
    .footer-right p {
        font-size: 0.7rem;
    }
}
