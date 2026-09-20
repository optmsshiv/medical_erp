/* Optms Rx — runtime configuration
   backend: true  → UI hydrates from the PHP/MySQL API (api/v1), session login required
   backend: false → pure offline demo driven by assets/js/data.js            */
window.MF_CONFIG = {
    backend: true,
    apiBase: 'api/v1',
};