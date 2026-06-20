import React from 'react';
import { createRoot } from 'react-dom/client';
import PlanningViewer from './components/PlanningViewer';

const element = document.getElementById('planning-viewer');

if (element) {
    createRoot(element).render(<PlanningViewer />);
}