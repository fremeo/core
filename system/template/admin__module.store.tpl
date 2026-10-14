{block name="inner_body"}
    {*Todo: Eine Suche diese Packgist Classe verwendet um passende Module mit de Tag frfemeo zu suchen. Es muss ein Sch-text box sein und eine ausgabe der treffer*}
    <form method="post">
        <input type="hidden" name="R[Action]" value='search'>
        <div class="mb-3">
            <label for="searchQuery" class="form-label">Suche nach Modulen</label>
            <input type="text" class="form-control" id="searchQuery" name="R[Search]" value="{$D.R.Search|escape}" placeholder="Suchbegriff eingeben">
        </div>
        <button type="submit" class="btn btn-primary">Suchen</button>
    </form>

    {if $D.R.ComposerOutput}
        <div class="alert mt-3 {if $D.R.ComposerOutput.success}alert-success{else}alert-danger{/if}">
            <pre class="mb-0" style="white-space:pre-wrap;">{$D.R.ComposerOutput.output|escape}</pre>
        </div>
    {/if}


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
            {foreach $D.R.Module.D|default:[] as $module}
            <tr>
                <td>{$module['name']|escape}</td>
                <td>{$module['description']|escape}</td>
                <td>{$module['version_latest']|escape}</td>
                <td>
                {if $module['installed']}
                    <span class="badge text-bg-secondary">Installiert {$module['installed_version']|escape}</span>
                {else}
                    <form method="post" class="d-inline" onsubmit="return confirm('{$module['name']|escape:'javascript'} installieren?')">
                        <input type="hidden" name="R[Action]" value="install">
                        <input type="hidden" name="R[Search]" value="{$D.R.Search|escape}">
                        <input type="hidden" name="R[Module][Id]" value="{$module['name']|escape}">
                        <input type="hidden" name="R[Module][Version]" value="{$module['version_latest']|escape}">
                        <button type="submit" class="btn btn-success btn-sm">Installieren</button>
                    </form>
                {/if}
                </td>
            </tr>
            {/foreach}
        </tbody>
    </table>

{/block}