/**
 * TalentFlow Live Coding Widget
 * Adds a floating button to launch Live Coding popup
 * 
 * Usage:
 * <script src="http://localhost:8000/js/live-coding-widget.js"></script>
 * <script>
 *   TalentFlowLivecoding.init({
 *     entretienId: 34,
 *     position: 'bottom-right' // or any corner
 *   });
 * </script>
 */

window.TalentFlowLivecoding = (function () {
    const WIDGET_ID = 'talentflow-lc-widget';
    const CONFIG_DEFAULT = {
        entretienId: null,
        position: 'bottom-right',
        baseUrl: 'http://127.0.0.1:8000',
        title: '💻 Code',
        tooltip: 'Ouvrir Live Coding'
    };

    let config = CONFIG_DEFAULT;

    function createWidget() {
        // Remove existing widget if any
        const existing = document.getElementById(WIDGET_ID);
        if (existing) existing.remove();

        // Create container
        const container = document.createElement('div');
        container.id = WIDGET_ID;
        container.style.cssText = `
            position: fixed;
            ${getPositionCSS()}
            z-index: 999999;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        `;

        // Create button
        const button = document.createElement('button');
        button.innerHTML = config.title;
        button.title = config.tooltip;
        button.style.cssText = `
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.4);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        `;

        button.addEventListener('mouseover', () => {
            button.style.transform = 'scale(1.05)';
            button.style.boxShadow = '0 6px 20px rgba(74, 144, 226, 0.6)';
        });

        button.addEventListener('mouseout', () => {
            button.style.transform = 'scale(1)';
            button.style.boxShadow = '0 4px 12px rgba(74, 144, 226, 0.4)';
        });

        button.addEventListener('click', () => {
            openLiveCoding();
        });

        container.appendChild(button);
        document.body.appendChild(container);
    }

    function getPositionCSS() {
        const positions = {
            'bottom-right': 'bottom: 24px; right: 24px;',
            'bottom-left': 'bottom: 24px; left: 24px;',
            'top-right': 'top: 24px; right: 24px;',
            'top-left': 'top: 24px; left: 24px;',
        };
        return positions[config.position] || positions['bottom-right'];
    }

    function openLiveCoding() {
        if (!config.entretienId) {
            alert('Entretien ID non configuré');
            return;
        }

        const popupUrl = `${config.baseUrl}/live-coding/popup/${config.entretienId}`;
        const features = 'width=1200,height=800,resizable=yes,scrollbars=yes';
        
        const popup = window.open(popupUrl, 'TalentFlowLiveCoding', features);
        
        if (!popup) {
            alert('Les popups doivent être activées pour utiliser Live Coding');
            return;
        }

        popup.focus();

        // Listen for messages from popup
        window.addEventListener('message', (event) => {
            if (event.origin !== config.baseUrl) return;
            
            if (event.data.type === 'LIVE_CODING_READY') {
                console.log('Live Coding popup ready for entretien:', event.data.entretienId);
                // Notify parent/external apps
                if (window.parent !== window) {
                    window.parent.postMessage({
                        type: 'TALENTFLOW_LIVE_CODING_READY',
                        entretienId: event.data.entretienId
                    }, '*');
                }
            }
        });
    }

    return {
        init: function(userConfig = {}) {
            config = { ...CONFIG_DEFAULT, ...userConfig };
            createWidget();
        },
        
        open: function(entretienId) {
            config.entretienId = entretienId;
            openLiveCoding();
        },
        
        destroy: function() {
            const widget = document.getElementById(WIDGET_ID);
            if (widget) widget.remove();
        },
        
        getConfig: function() {
            return { ...config };
        }
    };
})();

// Auto-detect entretien ID from URL if available
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const entretienId = urlParams.get('entretienId');
    
    if (entretienId && window.TalentFlowLivecodingAutoInit !== false) {
        TalentFlowLivecoding.init({
            entretienId: parseInt(entretienId)
        });
    }
});
