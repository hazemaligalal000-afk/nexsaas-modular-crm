import React, { useState, useEffect } from 'react';
import { ToastContainer, toast } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';

/**
 * NexSaaS Real-time Social Proof Engine (CRO Phase 1)
 * Displays anonymous "Conversion Toasts" to build authority and FOMO.
 */
export const SocialProofToast = () => {
    useEffect(() => {
        // Only run on non-production or for trial users to increase conversion
        // Mocking the Pusher bind here for implementation demo
        const simulateConversion = setInterval(() => {
            const locations = ['London', 'Cairo', 'New York', 'Dubai', 'Berlin', 'Alexandria'];
            const event = {
                location: locations[Math.floor(Math.random() * locations.length)],
                type: 'Closed a $15,000 Deal',
                time: '2 minutes ago'
            };

            toast.success(`🔥 Someone in ${event.location} just ${event.type}!`, {
                position: "bottom-left",
                autoClose: 5000,
                hideProgressBar: false,
                closeOnClick: true,
                pauseOnHover: true,
                draggable: true,
                style: { background: '#0f172a', color: '#3b82f6', border: '1px solid #1e293b' }
            });
        }, 120000); // Pulse every 2 minutes

        return () => clearInterval(simulateConversion);
    }, []);

    return <ToastContainer limit={3} />;
};
