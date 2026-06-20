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
        </div>
    );
}

function uniqueValues(items, key) {
    return [...new Set(items.map((item) => item[key]))]
        .filter((value) => value && value !== '-')
        .sort();
}