/**
 * Smart Order Placement with Fallback Handler
 * 
 * Handles order placement with automatic fallback prompts
 * and transparent provider switching (hides provider names)
 */

class SmartOrderHandler {
    static async placeOrder(serviceId, link, quantity, endpoint = 'place-order.php') {
        try {
            // First attempt with primary provider
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    service_id: serviceId,
                    link: link,
                    quantity: quantity,
                }),
            });

            const result = await response.json();

            // Success - return immediately
            if (result.success) {
                return { success: true, ...result };
            }

            // Check if fallback is available and ask user
            if (result.provider_unavailable && result.can_retry_alt) {
                return await this.promptForFallback(serviceId, link, quantity, endpoint);
            }

            // Both providers failed - suggest alternatives
            if (result.both_failed && result.suggest_alternatives) {
                return await this.promptForAlternatives(result.service_id, result.platform);
            }

            // Generic error
            return { success: false, message: result.message };

        } catch (error) {
            console.error('Order placement error:', error);
            return { success: false, message: 'Kosa la mtandao: ' + error.message };
        }
    }

    static async promptForFallback(serviceId, link, quantity, endpoint) {
        return new Promise((resolve) => {
            const confirmed = confirm(
                'Huduma yetu ya kawaida haipatikani sasa.\n\n' +
                'Je, ungependa kutumia huduma ya mtu mwingine ili kukamilisha order hii?\n\n' +
                'Gharama itabaki sawa lakini huduma inaweza kutekelezwa haraka zaidi.'
            );

            if (!confirmed) {
                return resolve({ 
                    success: false, 
                    message: 'Order ilisitishwa. Jaribu tena baadae.',
                    cancelled_by_user: true,
                });
            }

            // User approved - retry with fallback
            return this.retryWithFallback(serviceId, link, quantity, endpoint)
                .then(resolve)
                .catch(err => resolve({ 
                    success: false, 
                    message: 'Kosa limejitokeza: ' + err.message 
                }));
        });
    }

    static async retryWithFallback(serviceId, link, quantity, endpoint) {
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    service_id: serviceId,
                    link: link,
                    quantity: quantity,
                    use_fallback: true,  // Signal to use fallback provider
                }),
            });

            const result = await response.json();

            if (result.success) {
                // Success with fallback
                return {
                    success: true,
                    ...result,
                    fallback_used: true,
                };
            }

            // Fallback also failed - suggest alternatives
            if (result.both_failed) {
                return await this.promptForAlternatives(serviceId, result.platform);
            }

            return { success: false, message: result.message };

        } catch (error) {
            throw error;
        }
    }

    static async promptForAlternatives(serviceId, platform) {
        return new Promise((resolve) => {
            const confirmed = confirm(
                'Huduma hii haipatikani kwa sasa.\n\n' +
                'Je, ungependa kuangalia huduma nyingine sawa katika jamii hii?'
            );

            if (!confirmed) {
                return resolve({ 
                    success: false, 
                    message: 'Huduma hii haipatikani sasa. Jaribu baadae.',
                });
            }

            // Fetch similar services
            return this.fetchAlternatives(serviceId, platform)
                .then(alternatives => {
                    if (alternatives.length === 0) {
                        return resolve({ 
                            success: false, 
                            message: 'Huduma nyingine hazipo kwa sasa.' 
                        });
                    }

                    return resolve({
                        success: false,
                        similar_available: true,
                        alternatives: alternatives,
                        message: 'Huduma nyingine zinapatikana - tafadhali chagua moja kwenye orodha.',
                    });
                })
                .catch(err => resolve({ 
                    success: false, 
                    message: 'Kosa limejitokeza: ' + err.message 
                }));
        });
    }

    static async fetchAlternatives(serviceId, platform) {
        try {
            const params = new URLSearchParams();
            if (serviceId) params.append('service_id', serviceId);
            if (platform) params.append('platform', platform);

            const response = await fetch('get-similar-services.php?' + params);
            const result = await response.json();

            if (result.success && result.services) {
                return result.services;
            }

            return [];

        } catch (error) {
            console.error('Error fetching alternatives:', error);
            return [];
        }
    }

    /**
     * Display fallback success message (hides provider name)
     */
    static showFallbackSuccess(orderData) {
        let message = 'Order imefanikiwa!';
        
        if (orderData.fallback_used) {
            message += '\n\nOrder ilichakatwa kupitia huduma mbadala lakini gharama ni sawa.';
        }

        if (orderData.cost) {
            message += '\n\nGharama: TSh ' + this.formatNumber(orderData.cost);
        }

        if (orderData.new_balance !== undefined) {
            message += '\nSalio jipya: TSh ' + this.formatNumber(orderData.new_balance);
        }

        return message;
    }

    /**
     * Format number with thousand separators
     */
    static formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SmartOrderHandler;
}
