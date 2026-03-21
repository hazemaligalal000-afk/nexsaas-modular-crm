import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

/**
 * Enterprise Keyboard Shortcut Manager
 * Requirement: 6.145 - Advanced Power User UX
 * Features: G+L (Leads), G+D (Deals), G+H (Home), C (Compose Email)
 */
export default function ShortcutManager() {
    const navigate = useNavigate();

    useEffect(() => {
        let keysPressed = {};

        const handleKeyDown = (e) => {
            keysPressed[e.key.toLowerCase()] = true;

            // Simple navigation combos: "g" + [module]
            if (keysPressed['g']) {
                if (keysPressed['l']) navigate('/leads');
                if (keysPressed['d']) navigate('/pipeline');
                if (keysPressed['i']) navigate('/inbox');
                if (keysPressed['s']) navigate('/settings');
            }

            // Quick actions
            if (e.key === 'c' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                alert('Opening AI Email Drafter...');
            }
        };

        const handleKeyUp = (e) => {
            delete keysPressed[e.key.toLowerCase()];
        };

        window.addEventListener('keydown', handleKeyDown);
        window.addEventListener('keyup', handleKeyUp);
        return () => {
            window.removeEventListener('keydown', handleKeyDown);
            window.removeEventListener('keyup', handleKeyUp);
        };
    }, [navigate]);

    return null;
}
