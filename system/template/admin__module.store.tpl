{block name="inner_body"}
    {*Todo: Eine Suche diese Packgist Classe verwendet um passende Module mit de Tag frfemeo zu suchen. Es muss ein Sch-text box sein und eine ausgabe der treffer*}
    <form method="post">
        <input type="hidden" name="R[Action]" value='search'>
        <div class="mb-3">
            <label for="searchQuery" class="form-label">Suche nach Modulen</label>
            <input type="text" class="form-control" id="searchQuery" name="R[Search]" placeholder="Suchbegriff eingeben">
        </div>
        <button type="submit" class="btn btn-primary">Suchen</button>


    <table class="table table-striped mt-3">
        <thead>
            <tr>
                <th scope="col">Name</th>
                <th scope="col">Beschreibung</th>
                <th scope="col">Version</th>
                <th scope="col">Aktion</th>
            </tr>
        </thead>
        <tbody>
            {foreach $D['R']['Module']['D'] as $module}
            <tr>
                <td>{$module['name']}</td>
                <td>{$module['description']}</td>
                <td>{$module['version_latest']}</td>
                <td><a href="?R[Page]=admin__module.store&R[Action]=install&R[ModuleId]=fremeo/core&R[Module][Id]={$module['name']}&R[Module][Version]={$module['version_latest']}" class="btn btn-success btn-sm">Installieren</a></td>
            </tr>
            {/foreach}
        </tbody>
    </table>

{/block}