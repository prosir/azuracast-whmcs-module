{* AzuraCast client panel. Uses WHMCS/Bootstrap classes so it follows the active client theme. *}
{if $azuracastError}
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle fa-fw" aria-hidden="true"></i>
        <strong>We could not load your radio station.</strong>
        <div class="small">{$azuracastError|escape}</div>
    </div>
{else}
    <div class="panel panel-default card mb-4">
        <div class="panel-heading card-header">
            <div class="row align-items-center">
                <div class="col-xs-8 col-sm-8">
                    <h3 class="panel-title card-title mb-0">
                        <i class="fas fa-broadcast-tower fa-fw" aria-hidden="true"></i>
                        {$azuracastStationName|escape}
                    </h3>
                </div>
                <div class="col-xs-4 col-sm-4 text-right">
                    {if $azuracastEnabled && $azuracastFrontendRunning && $azuracastBackendRunning}
                        <span class="label label-success badge badge-success">Online</span>
                    {elseif !$azuracastEnabled}
                        <span class="label label-default badge badge-secondary">Suspended</span>
                    {else}
                        <span class="label label-warning badge badge-warning">Starting / Offline</span>
                    {/if}
                </div>
            </div>
        </div>

        <div class="panel-body card-body">
            <p class="text-muted">
                Your radio station is ready. Use the button below to open the AzuraCast control panel.
            </p>

            <div class="row text-center">
                <div class="col-xs-4 col-sm-4">
                    <div class="well well-sm border rounded p-3">
                        <div class="small text-muted">Storage</div>
                        <strong>{if $azuracastStorageMb == 0}Unlimited{else}{$azuracastStorageMb|escape} MB{/if}</strong>
                    </div>
                </div>
                <div class="col-xs-4 col-sm-4">
                    <div class="well well-sm border rounded p-3">
                        <div class="small text-muted">Listeners</div>
                        <strong>{if $azuracastMaxListeners == 0}Unlimited{else}{$azuracastMaxListeners|escape}{/if}</strong>
                    </div>
                </div>
                <div class="col-xs-4 col-sm-4">
                    <div class="well well-sm border rounded p-3">
                        <div class="small text-muted">Bitrate</div>
                        <strong>{if $azuracastMaxBitrate == 0}Unlimited{else}{$azuracastMaxBitrate|escape} kbps{/if}</strong>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <a href="{$azuracastManageUrl|escape:'html'}"
                   class="btn btn-primary btn-lg"
                   target="_blank"
                   rel="noopener noreferrer">
                    <i class="fas fa-external-link-alt fa-fw" aria-hidden="true"></i>
                    Open AzuraCast Control Panel
                </a>
                <p class="help-block small text-muted mt-2">
                    You may be asked to sign in to AzuraCast.
                </p>
            </div>
        </div>

        {if $azuracastShortName}
            <div class="panel-footer card-footer text-muted small">
                Station ID: <code>{$azuracastShortName|escape}</code>
            </div>
        {/if}
    </div>
{/if}
