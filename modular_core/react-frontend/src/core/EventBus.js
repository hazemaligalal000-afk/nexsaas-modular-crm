/**
 * Event Bus (Pub/Sub)
 * Used for inter-module communication in the frontend.
 * E.g., The 'Products' module fires 'inventory.low_stock' and the 'Dashboard' module catches it to render an alert.
 */

class EventBus {
    constructor() {
        this.listeners = {};
    }

    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);

        // Return unsubscribe function
        return () => {
            this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
        };
    }

    emit(event, payload = {}) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => {
                try {
                    callback(payload);
                } catch (err) {
                    console.error(`Error executing listener for generic event [${event}]:`, err);
                }
            });
        }
    }
}

export const eventBus = new EventBus();
