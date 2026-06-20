import React from 'react';
import { createRoot } from 'react-dom/client';

import PlanningViewer from './components/PlanningViewer';
import ExcelImportPreview from './components/ExcelImportPreview';

const planningElement = document.getElementById('planning-viewer');

if (planningElement) {
    createRoot(planningElement).render(<PlanningViewer />);
}

const importPreviewElement = document.getElementById('excel-import-preview');

if (importPreviewElement) {
    const inputId = importPreviewElement.dataset.inputId || 'excel_file';

    createRoot(importPreviewElement).render(
        <ExcelImportPreview inputId={inputId} />
    );
}