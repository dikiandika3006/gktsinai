import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

const config = JSON.parse(document.getElementById('absen-config').textContent);

createRoot(document.getElementById('absen-root')).render(
    <StrictMode>
        <App config={config} />
    </StrictMode>,
);
