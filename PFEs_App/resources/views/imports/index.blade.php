<x-layout title="Importation des données">

    <h1>Importation des données PFE</h1>

    <hr>

    <form
        action="{{ route('imports.all') }}"
        method="POST"
        enctype="multipart/form-data">

        @csrf

        <h2>Liste des etudiants</h2>

        <input
            type="file"
            name="students_file">

        @error('students_file')
            <p class="error">
                {{ $message }}
            </p>
        @enderror

        <br><br>

        <h2>Liste des professeurs</h2>

        <input
            type="file"
            name="professeurs_file">

        @error('professeurs_file')
            <p class="error">
                {{ $message }}
            </p>
        @enderror

        <br><br>

        <h2>Date de début des soutenances</h2>

        <input
            type="date"
            name="date_soutenance">

        @error('date_soutenance')
            <p class="error">
                {{ $message }}
            </p>
        @enderror

        <br><br>

        <h2>Salles disponibles</h2>

        <label>
            <input type="checkbox" name="salles[]" value="Salle A">Salle A
        </label>

        <br>

        <label>
            <input type="checkbox" name="salles[]" value="Salle B">Salle B
        </label>

        <br>
        
        <label>
            <input type="checkbox" name="salles[]" value="Salle C">Salle C
        </label>

        <br>

        <label>
            <input type="checkbox" name="salles[]" value="Salle D">Salle D
        </label>

        <br>

        <label>
            <input type="checkbox" name="salles[]" value="Salle E">Salle E
        </label>
    
        @error('salles')
            <p class="error">
                {{ $message }}
            </p>
        @enderror

        <br><br>

        <button type="submit">
            Importer toutes les données
        </button>

    </form>

</x-layout>