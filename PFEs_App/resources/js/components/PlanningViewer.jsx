import React, { useEffect, useMemo, useState } from 'react';

export default function PlanningViewer() {
    const [planning, setPlanning] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const [search, setSearch] = useState('');
    const [date, setDate] = useState('');
    const [salle, setSalle] = useState('');
    const [filiere, setFiliere] = useState('');
    const [professeur, setProfesseur] = useState('');

    const [viewMode, setViewMode] = useState('table');

    useEffect(() => {
        fetch('/api/planning')
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Impossible de charger le planning.');
                }

                return response.json();
            })
            .then((data) => {
                setPlanning(data);
                setLoading(false);
            })
            .catch((err) => {
                setError(err.message);
                setLoading(false);
            });
    }, []);

    const dates = useMemo(() => uniqueValues(planning, 'date'), [planning]);
    const salles = useMemo(() => uniqueValues(planning, 'salle'), [planning]);
    const filieres = useMemo(() => uniqueValues(planning, 'filiere'), [planning]);

    const professeurs = useMemo(() => {
        const values = [];

        planning.forEach((item) => {
            values.push(item.encadrant, item.jury1, item.jury2);
        });

        return [...new Set(values)]
            .filter((value) => value && value !== '-')
            .sort();
    }, [planning]);

    const filteredPlanning = useMemo(() => {
        const searchValue = search.toLowerCase().trim();

        return planning.filter((item) => {
            const searchText = [
                item.date,
                item.heure,
                item.salle,
                item.pfe,
                item.etudiant,
                item.filiere,
                item.langue,
                item.encadrant,
                item.jury1,
                item.jury2,
            ].join(' ').toLowerCase();

            const matchSearch = searchValue === '' || searchText.includes(searchValue);
            const matchDate = date === '' || item.date === date;
            const matchSalle = salle === '' || item.salle === salle;
            const matchFiliere = filiere === '' || item.filiere === filiere;
            const matchProfesseur =
                professeur === ''
                || item.encadrant === professeur
                || item.jury1 === professeur
                || item.jury2 === professeur;

            return matchSearch
                && matchDate
                && matchSalle
                && matchFiliere
                && matchProfesseur;
        });
    }, [planning, search, date, salle, filiere, professeur]);

    function resetFilters() {
        setSearch('');
        setDate('');
        setSalle('');
        setFiliere('');
        setProfesseur('');
    }

    if (loading) {
        return (
            <div className="alert alert-info">
                Chargement du planning...
            </div>
        );
    }

    if (error) {
        return (
            <div className="alert alert-danger">
                {error}
            </div>
        );
    }

    if (planning.length === 0) {
        return (
            <div className="alert alert-warning">
                Aucun planning n'est encore généré.
            </div>
        );
    }

    return (
        <div>
            <div className="row g-3 mb-4">
                <div className="col-md-3">
                    <div className="border rounded p-3 bg-light">
                        <div className="text-muted">Total soutenances</div>
                        <div className="fs-4 fw-bold">{planning.length}</div>
                    </div>
                </div>

                <div className="col-md-3">
                    <div className="border rounded p-3 bg-light">
                        <div className="text-muted">Résultats affichés</div>
                        <div className="fs-4 fw-bold">{filteredPlanning.length}</div>
                    </div>
                </div>

                <div className="col-md-3">
                    <div className="border rounded p-3 bg-light">
                        <div className="text-muted">Salles utilisées</div>
                        <div className="fs-4 fw-bold">{salles.length}</div>
                    </div>
                </div>

                <div className="col-md-3">
                    <div className="border rounded p-3 bg-light">
                        <div className="text-muted">Filières</div>
                        <div className="fs-4 fw-bold">{filieres.length}</div>
                    </div>
                </div>
            </div>

            <div className="border rounded p-3 mb-4 bg-light">
                <div className="row g-3">
                    <div className="col-md-4">
                        <label className="form-label fw-semibold">Recherche</label>
                        <input
                            type="text"
                            className="form-control"
                            placeholder="Étudiant, PFE, professeur..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>

                    <div className="col-md-2">
                        <label className="form-label fw-semibold">Date</label>
                        <select
                            className="form-select"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                        >
                            <option value="">Toutes</option>
                            {dates.map((value) => (
                                <option key={value} value={value}>{value}</option>
                            ))}
                        </select>
                    </div>

                    <div className="col-md-2">
                        <label className="form-label fw-semibold">Salle</label>
                        <select
                            className="form-select"
                            value={salle}
                            onChange={(e) => setSalle(e.target.value)}
                        >
                            <option value="">Toutes</option>
                            {salles.map((value) => (
                                <option key={value} value={value}>{value}</option>
                            ))}
                        </select>
                    </div>

                    <div className="col-md-2">
                        <label className="form-label fw-semibold">Filière</label>
                        <select
                            className="form-select"
                            value={filiere}
                            onChange={(e) => setFiliere(e.target.value)}
                        >
                            <option value="">Toutes</option>
                            {filieres.map((value) => (
                                <option key={value} value={value}>{value}</option>
                            ))}
                        </select>
                    </div>

                    <div className="col-md-2">
                        <label className="form-label fw-semibold">Professeur</label>
                        <select
                            className="form-select"
                            value={professeur}
                            onChange={(e) => setProfesseur(e.target.value)}
                        >
                            <option value="">Tous</option>
                            {professeurs.map((value) => (
                                <option key={value} value={value}>{value}</option>
                            ))}
                        </select>
                    </div>
                </div>

                <button
                    type="button"
                    className="btn btn-outline-secondary mt-3"
                    onClick={resetFilters}
                >
                    Réinitialiser les filtres
                </button>
            </div>

            <div className="d-flex gap-2 mb-3">
                <button
                    type="button"
                    className={`btn ${viewMode === 'table' ? 'btn-primary' : 'btn-outline-primary'}`}
                    onClick={() => setViewMode('table')}
                >
                    Table View
                </button>

                <button
                    type="button"
                    className={`btn ${viewMode === 'timetable' ? 'btn-primary' : 'btn-outline-primary'}`}
                    onClick={() => setViewMode('timetable')}
                >
                    Timetable View
                </button>
            </div>

            {viewMode === 'table' ? (
                <>
                    <div className="table-responsive">
                        <table className="table table-bordered table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Heure</th>
                                    <th>Salle</th>
                                    <th>PFE</th>
                                    <th>Étudiant</th>
                                    <th>Filière</th>
                                    <th>Encadrant</th>
                                    <th>Jury 1</th>
                                    <th>Jury 2</th>
                                    <th>Alerte</th>
                                </tr>
                            </thead>

                            <tbody>
                                {filteredPlanning.map((item) => (
                                    <tr key={item.id}>
                                        <td>{item.date}</td>
                                        <td>{item.heure}</td>
                                        <td>{item.salle}</td>
                                        <td>{item.pfe}</td>
                                        <td>{item.etudiant}</td>
                                        <td>
                                            <span className="badge bg-primary">
                                                {item.filiere}
                                            </span>
                                        </td>
                                        <td>{item.encadrant}</td>
                                        <td>{item.jury1}</td>
                                        <td>{item.jury2}</td>
                                        <td>
                                            {item.alerte_anglais ? (
                                                <span className="badge bg-warning text-dark">
                                                    Anglais sans prof anglais
                                                </span>
                                            ) : (
                                                <span className="badge bg-success">
                                                    OK
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {filteredPlanning.length === 0 && (
                        <div className="alert alert-warning mt-3">
                            Aucun résultat ne correspond aux filtres sélectionnés.
                        </div>
                    )}
                </>
            ) : (
                <TimetableView planning={filteredPlanning} />
            )}
        </div>
    );
}

function uniqueValues(items, key) {
    return [...new Set(items.map((item) => item[key]))]
        .filter((value) => value && value !== '-')
        .sort();
}

function TimetableView({ planning }) {
    const [selectedItem, setSelectedItem] = useState(null);

    const dates = uniqueValues(planning, 'date');
    const salles = uniqueValues(planning, 'salle');
    const heures = uniqueValues(planning, 'heure');

    if (planning.length === 0) {
        return (
            <div className="alert alert-warning">
                Aucun résultat ne correspond aux filtres sélectionnés.
            </div>
        );
    }

    return (
        <div>
            {dates.map((currentDate) => {
                const planningForDate = planning.filter((item) => item.date === currentDate);

                return (
                    <div className="card border-0 shadow-sm mb-4" key={currentDate}>
                        <div className="card-header bg-light">
                            <strong>Date : {currentDate}</strong>
                        </div>

                        <div className="card-body">
                            <div className="table-responsive">
                                <table className="table table-bordered align-middle text-center">
                                    <thead>
                                        <tr>
                                            <th style={{ minWidth: '90px' }}>Heure</th>

                                            {salles.map((room) => (
                                                <th key={room} style={{ minWidth: '220px' }}>
                                                    {room}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {heures.map((hour) => (
                                            <tr key={hour}>
                                                <th>{hour}</th>

                                                {salles.map((room) => {
                                                    const item = planningForDate.find((soutenance) => {
                                                        return soutenance.heure === hour && soutenance.salle === room;
                                                    });

                                                    return (
                                                        <td key={`${currentDate}-${hour}-${room}`}>
                                                            {item ? (
                                                                <button
                                                                    type="button"
                                                                    className="btn btn-light border w-100 text-start"
                                                                    onClick={() => setSelectedItem(item)}
                                                                >
                                                                    <div className="fw-bold">
                                                                        {item.pfe}
                                                                    </div>

                                                                    <div className="small text-muted">
                                                                        {item.etudiant}
                                                                    </div>

                                                                    <div className="mt-2">
                                                                        <span className="badge bg-primary me-1">
                                                                            {item.filiere}
                                                                        </span>

                                                                        {item.alerte_anglais && (
                                                                            <span className="badge bg-warning text-dark">
                                                                                Alerte anglais
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                </button>
                                                            ) : (
                                                                <span className="text-muted small">
                                                                    Libre
                                                                </span>
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                );
            })}

            {selectedItem && (
                <div
                    className="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                    style={{ background: 'rgba(0, 0, 0, 0.45)', zIndex: 1050 }}
                    onClick={() => setSelectedItem(null)}
                >
                    <div
                        className="card shadow-lg"
                        style={{ width: '520px', maxWidth: '95%' }}
                        onClick={(event) => event.stopPropagation()}
                    >
                        <div className="card-header d-flex justify-content-between align-items-center">
                            <strong>Détails de la soutenance</strong>

                            <button
                                type="button"
                                className="btn-close"
                                onClick={() => setSelectedItem(null)}
                            />
                        </div>

                        <div className="card-body">
                            <p><strong>Date :</strong> {selectedItem.date}</p>
                            <p><strong>Heure :</strong> {selectedItem.heure}</p>
                            <p><strong>Salle :</strong> {selectedItem.salle}</p>
                            <p><strong>PFE :</strong> {selectedItem.pfe}</p>
                            <p><strong>Étudiant :</strong> {selectedItem.etudiant}</p>
                            <p><strong>Filière :</strong> {selectedItem.filiere}</p>
                            <p><strong>Langue :</strong> {selectedItem.langue}</p>
                            <hr />
                            <p><strong>Encadrant :</strong> {selectedItem.encadrant}</p>
                            <p><strong>Jury 1 :</strong> {selectedItem.jury1}</p>
                            <p><strong>Jury 2 :</strong> {selectedItem.jury2}</p>

                            {selectedItem.alerte_anglais && (
                                <div className="alert alert-warning mt-3">
                                    Ce PFE est en anglais mais aucun professeur anglais n’est affecté.
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}