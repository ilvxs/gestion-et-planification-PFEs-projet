import React, { useEffect, useState } from 'react';
import * as XLSX from 'xlsx';

const REQUIRED_COLUMNS = {
    cne: ['cne'],
    nom: ['nom'],
    prenom: ['prenom', 'prénom'],
    email: [
        'email',
        'mail',
        'email academique',
        'email académique',
        'mail academique',
        'mail académique',
        'email personnel',
        'mail personnel',
    ],
    filiere: ['filiere', 'filière'],
    langue: ['langue', 'language'],
};

const OPTIONAL_COLUMNS = {
    sujet: ['sujet', 'theme', 'thème', 'titre', 'intitule', 'intitulé'],
    emailAcademique: [
        'email academique',
        'email académique',
        'mail academique',
        'mail académique',
    ],
    emailPersonnel: [
        'email personnel',
        'mail personnel',
    ],
};

const SHEET_ALIASES = {
    etudiants: ['etudiants', 'étudiants', 'etudiant', 'étudiant'],
    professeurs: ['professeurs', 'professeur', 'profs', 'prof'],
    salles: ['salles', 'salle'],
};

export default function ExcelImportPreview({ inputId }) {
    const [fileName, setFileName] = useState('');
    const [preview, setPreview] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        const input = document.getElementById(inputId);

        if (!input) {
            setError(`Input file introuvable avec l'id "${inputId}".`);
            return;
        }

        const handleChange = async (event) => {
            const file = event.target.files?.[0];

            setError('');
            setPreview(null);

            if (!file) {
                setFileName('');
                return;
            }

            setFileName(file.name);

            try {
                const data = await file.arrayBuffer();

                const workbook = XLSX.read(data, {
                    type: 'array',
                });

                const result = analyzeWorkbook(workbook);

                setPreview(result);
            } catch (err) {
                setError("Impossible de lire le fichier Excel. Vérifiez que le fichier est valide.");
            }
        };

        input.addEventListener('change', handleChange);

        return () => {
            input.removeEventListener('change', handleChange);
        };
    }, [inputId]);

    if (error) {
        return (
            <div className="alert alert-danger mt-3">
                {error}
            </div>
        );
    }

    if (!preview) {
        return (
            <div className="alert alert-secondary mt-3">
                Sélectionnez un fichier Excel pour afficher l’aperçu avant importation.
            </div>
        );
    }

    return (
        <div className="mt-4">
            <div className="card border-0 shadow-sm">
                <div className="card-header bg-light">
                    <strong>Aperçu du fichier Excel</strong>
                    <div className="text-muted small">{fileName}</div>
                </div>

                <div className="card-body">
                    <h5 className="mb-3">1. Feuilles détectées</h5>

                    <div className="row g-3 mb-4">
                        <SheetStatus
                            label="Étudiants"
                            found={preview.sheets.etudiants.found}
                            sheetName={preview.sheets.etudiants.name}
                            count={preview.sheets.etudiants.count}
                        />

                        <SheetStatus
                            label="Professeurs"
                            found={preview.sheets.professeurs.found}
                            sheetName={preview.sheets.professeurs.name}
                            count={preview.sheets.professeurs.count}
                        />

                        <SheetStatus
                            label="Salles"
                            found={preview.sheets.salles.found}
                            sheetName={preview.sheets.salles.name}
                            count={preview.sheets.salles.count}
                        />
                    </div>

                    <h5 className="mb-3">2. Colonnes étudiants</h5>

                    {preview.etudiants.sheetFound ? (
                        <>
                            <div className="row g-2 mb-3">
                                {preview.etudiants.required.map((column) => (
                                    <div className="col-md-4" key={column.key}>
                                        <div className={`p-2 rounded border ${column.found ? 'bg-success-subtle' : 'bg-danger-subtle'}`}>
                                            {column.found ? '✅' : '❌'} {column.label}
                                            {column.found && (
                                                <span className="text-muted small"> — {column.header}</span>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="mb-3">
                                <strong>Colonne optionnelle :</strong>{' '}
                                {preview.etudiants.sujet.found ? (
                                    <span className="badge bg-success">
                                        Sujet trouvé : {preview.etudiants.sujet.header}
                                    </span>
                                ) : (
                                    <span className="badge bg-warning text-dark">
                                        Sujet absent, il sera enregistré comme vide
                                    </span>
                                )}
                            </div>

                            <div className="mb-4">
                                <strong>Colonnes supplémentaires ignorées :</strong>{' '}
                                {preview.etudiants.extraColumns.length > 0 ? (
                                    preview.etudiants.extraColumns.map((column) => (
                                        <span className="badge bg-secondary me-1" key={column}>
                                            {column}
                                        </span>
                                    ))
                                ) : (
                                    <span className="text-muted">Aucune</span>
                                )}
                            </div>

                            {preview.etudiants.missing.length > 0 && (
                                <div className="alert alert-danger">
                                    Colonnes obligatoires manquantes :{' '}
                                    <strong>{preview.etudiants.missing.join(', ')}</strong>
                                </div>
                            )}

                            <h5 className="mb-3">3. Aperçu des premières lignes</h5>

                            {preview.etudiants.rows.length > 0 ? (
                                <div className="table-responsive">
                                    <table className="table table-bordered table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>CNE</th>
                                                <th>Nom</th>
                                                <th>Prénom</th>
                                                <th>Email</th>
                                                <th>Filière</th>
                                                <th>Sujet</th>
                                                <th>Langue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {preview.etudiants.rows.map((row, index) => (
                                                <tr key={index}>
                                                    <td>{row.cne || '-'}</td>
                                                    <td>{row.nom || '-'}</td>
                                                    <td>{row.prenom || '-'}</td>
                                                    <td>{row.email || '-'}</td>
                                                    <td>{row.filiere || '-'}</td>
                                                    <td>{row.sujet || '-'}</td>
                                                    <td>{row.langue || '-'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="alert alert-warning">
                                    Aucune ligne étudiant détectée.
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="alert alert-danger">
                            La feuille étudiants est introuvable.
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function SheetStatus({ label, found, sheetName, count }) {
    return (
        <div className="col-md-4">
            <div className={`p-3 rounded border ${found ? 'bg-success-subtle' : 'bg-danger-subtle'}`}>
                <div className="fw-bold">
                    {found ? '✅' : '❌'} {label}
                </div>

                {found ? (
                    <div className="small text-muted">
                        Feuille : {sheetName}<br />
                        Lignes détectées : {count}
                    </div>
                ) : (
                    <div className="small text-muted">
                        Feuille manquante
                    </div>
                )}
            </div>
        </div>
    );
}

function analyzeWorkbook(workbook) {
    const etudiantsSheetName = findSheetName(workbook, SHEET_ALIASES.etudiants);
    const professeursSheetName = findSheetName(workbook, SHEET_ALIASES.professeurs);
    const sallesSheetName = findSheetName(workbook, SHEET_ALIASES.salles);

    const etudiantsRows = etudiantsSheetName
        ? XLSX.utils.sheet_to_json(workbook.Sheets[etudiantsSheetName], {
            header: 1,
            defval: '',
        })
        : [];

    const professeursCount = countDataRows(workbook, professeursSheetName);
    const sallesCount = countDataRows(workbook, sallesSheetName);

    const etudiantsAnalysis = analyzeEtudiantsRows(etudiantsRows, Boolean(etudiantsSheetName));

    return {
        sheets: {
            etudiants: {
                found: Boolean(etudiantsSheetName),
                name: etudiantsSheetName,
                count: etudiantsAnalysis.count,
            },
            professeurs: {
                found: Boolean(professeursSheetName),
                name: professeursSheetName,
                count: professeursCount,
            },
            salles: {
                found: Boolean(sallesSheetName),
                name: sallesSheetName,
                count: sallesCount,
            },
        },
        etudiants: etudiantsAnalysis,
    };
}

function analyzeEtudiantsRows(rows, sheetFound) {
    if (!sheetFound) {
        return {
            sheetFound: false,
            count: 0,
            required: [],
            missing: [],
            sujet: {
                found: false,
                header: null,
            },
            extraColumns: [],
            rows: [],
        };
    }

    const headerIndex = findHeaderRowIndex(rows, REQUIRED_COLUMNS);

    if (headerIndex === null) {
        return {
            sheetFound: true,
            count: 0,
            required: Object.keys(REQUIRED_COLUMNS).map((key) => ({
                key,
                label: labelFor(key),
                found: false,
                header: null,
            })),
            missing: Object.keys(REQUIRED_COLUMNS),
            sujet: {
                found: false,
                header: null,
            },
            extraColumns: [],
            rows: [],
        };
    }

    const headerRow = rows[headerIndex];
    const columns = buildColumnMap(headerRow);

    const required = Object.entries(REQUIRED_COLUMNS).map(([key, aliases]) => {
        const index = findColumnIndex(columns, aliases);

        return {
            key,
            label: labelFor(key),
            found: index !== null,
            header: index !== null ? String(headerRow[index]).trim() : null,
        };
    });

    const missing = required
        .filter((item) => !item.found)
        .map((item) => item.label);

    const sujetIndex = findColumnIndex(columns, OPTIONAL_COLUMNS.sujet);

    const knownAliases = allKnownAliases();

    const extraColumns = headerRow
        .map((value) => String(value).trim())
        .filter((value) => value !== '')
        .filter((value) => !knownAliases.includes(normalizeLabel(value)));

    const dataRows = rows
        .slice(headerIndex + 1)
        .filter((row) => !isEmptyRow(row));

    const previewRows = dataRows
        .slice(0, 5)
        .map((row) => {
            const emailAcademique = getCellByAliases(row, columns, OPTIONAL_COLUMNS.emailAcademique);
            const emailPersonnel = getCellByAliases(row, columns, OPTIONAL_COLUMNS.emailPersonnel);
            const emailGeneral = getCellByAliases(row, columns, ['email', 'mail']);

            return {
                cne: getCellByAliases(row, columns, REQUIRED_COLUMNS.cne),
                nom: getCellByAliases(row, columns, REQUIRED_COLUMNS.nom),
                prenom: getCellByAliases(row, columns, REQUIRED_COLUMNS.prenom),
                email: emailAcademique || emailGeneral || emailPersonnel,
                filiere: getCellByAliases(row, columns, REQUIRED_COLUMNS.filiere),
                sujet: getCellByAliases(row, columns, OPTIONAL_COLUMNS.sujet),
                langue: getCellByAliases(row, columns, REQUIRED_COLUMNS.langue),
            };
        });

    return {
        sheetFound: true,
        count: dataRows.length,
        required,
        missing,
        sujet: {
            found: sujetIndex !== null,
            header: sujetIndex !== null ? String(headerRow[sujetIndex]).trim() : null,
        },
        extraColumns,
        rows: previewRows,
    };
}

function findSheetName(workbook, aliases) {
    const normalizedAliases = aliases.map(normalizeLabel);

    return workbook.SheetNames.find((sheetName) => {
        return normalizedAliases.includes(normalizeLabel(sheetName));
    });
}

function countDataRows(workbook, sheetName) {
    if (!sheetName) {
        return 0;
    }

    const rows = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], {
        header: 1,
        defval: '',
    });

    const headerIndex = rows.findIndex((row) => !isEmptyRow(row));

    if (headerIndex === -1) {
        return 0;
    }

    return rows
        .slice(headerIndex + 1)
        .filter((row) => !isEmptyRow(row))
        .length;
}

function findHeaderRowIndex(rows, requiredColumns) {
    for (let index = 0; index < rows.length; index++) {
        const row = rows[index];

        if (isEmptyRow(row)) {
            continue;
        }

        const columns = buildColumnMap(row);
        let found = 0;

        Object.values(requiredColumns).forEach((aliases) => {
            if (findColumnIndex(columns, aliases) !== null) {
                found++;
            }
        });

        if (found >= 4) {
            return index;
        }
    }

    return null;
}

function buildColumnMap(headerRow) {
    const columns = {};

    headerRow.forEach((value, index) => {
        const label = normalizeLabel(value);

        if (label !== '') {
            columns[label] = index;
        }
    });

    return columns;
}

function findColumnIndex(columns, aliases) {
    for (const alias of aliases) {
        const normalizedAlias = normalizeLabel(alias);

        if (Object.prototype.hasOwnProperty.call(columns, normalizedAlias)) {
            return columns[normalizedAlias];
        }
    }

    return null;
}

function getCellByAliases(row, columns, aliases) {
    const index = findColumnIndex(columns, aliases);

    if (index === null) {
        return '';
    }

    return String(row[index] ?? '').trim();
}

function isEmptyRow(row) {
    return row.every((value) => String(value).trim() === '');
}

function normalizeLabel(value) {
    return String(value)
        .trim()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[_-]/g, ' ')
        .replace(/\s+/g, ' ');
}

function allKnownAliases() {
    return [
        ...Object.values(REQUIRED_COLUMNS).flat(),
        ...Object.values(OPTIONAL_COLUMNS).flat(),
    ].map(normalizeLabel);
}

function labelFor(key) {
    const labels = {
        cne: 'CNE',
        nom: 'Nom',
        prenom: 'Prénom',
        email: 'Email',
        filiere: 'Filière',
        langue: 'Langue',
    };

    return labels[key] || key;
}