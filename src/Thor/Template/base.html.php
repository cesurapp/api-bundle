<!doctype html>
<html lang="en">
<head>
    <!-- Meta -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>API Documentation</title>

    <!-- Quasar -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900|Material+Icons" rel="stylesheet" type="text/css">
    <link href="https://cdn.jsdelivr.net/npm/quasar@2.14.1/dist/quasar.prod.css" rel="stylesheet" type="text/css">
    <style>
        .q-badge{
            font-size: 14px;
        }
        .q-item{
            min-height: 42px;
            padding: 6px 12px;
        }
        .bordered {
            border: 1px solid rgba(0,0,0,.1);
        }
        .gap-10 {
            gap: 10px;
        }
        .q-card__section--vert.q-dialog__message{
            padding-bottom: 8px;
        }
        .q-tooltip--style{
            font-size: 12px;
        }
        .rounded-borders{
            border-radius: 5px;
            overflow: hidden;
        }
        .q-item__section--side{
            padding-right: 10px;
        }
        .text-overflow{
            white-space: nowrap;
            overflow: hidden;
        }
        .q-expansion-item--expanded.main-expand > .q-expansion-item__container > .q-item--clickable,
        .q-expansion-item--expanded.main-expand > .q-expansion-item__container > .q-expansion-item__content > .q-card{
            background: rgba(1, 121, 239, 0.05) !important;
        }
        .sub-expand .q-item__section--avatar{
            min-width: 38px;
        }
        .q-table--dense .q-table tbody td, .q-table--dense .q-table tbody tr, .q-table--dense .q-table thead tr{
            height: 32px;
        }
        .q-table--dense .q-table td, .q-table--dense .q-table th{
            padding: 8px;
        }
    </style>
</head>
<body>
<div id="app" class="bg-grey-2">
    <q-layout view="hHh LpR lFf">
        <!--Header-->
        <q-header elevated class="bg-primary text-white">
            <q-toolbar>
                <q-btn dense flat round icon="description" @click="leftDrawerOpen = !leftDrawerOpen"></q-btn>
                <q-toolbar-title>API Documentation</q-toolbar-title>
                <q-btn flat icon="info" class="q-px-sm" @click="viewInfo"></q-btn>
                <q-btn-dropdown class="q-px-sm" flat icon="download" label="Download" :menu-offset="[0, 8]">
                    <q-list>
                        <q-item clickable v-close-popup :href="url" :key="name" v-for="(url, name) in downloadLinks">
                            <q-item-section><q-item-label>{{ name }}</q-item-label></q-item-section>
                        </q-item>
                    </q-list>
                </q-btn-dropdown>
            </q-toolbar>
        </q-header>

        <!--Sidebar-->
        <q-drawer show-if-above v-model="leftDrawerOpen" side="left" class="bg-grey-2 q-pa-md">
            <q-list bordered separator class="rounded-borders">
                <q-item class="bg-white" @click="scrollToId(toSlugify(title))" clickable v-ripple v-for="title in getSidebarTitles"><q-item-section>{{ title }}</q-item-section></q-item>
            </q-list>
        </q-drawer>

        <!--Content-->
        <q-page-container>
            <div class="q-px-md q-pb-lg">
                <div :key="title" v-for="(stack, title) in docData">
                    <h5 :id="toSlugify(title)" class="text-h6 text-weight-regular q-mx-none q-mb-sm q-mt-md header">{{ title }}</h5>
                    <q-list bordered class="rounded-borders">
                        <q-expansion-item group="items" :key="title + index" v-for="(item, index) in stack" header-class="bg-white" class='main-expand'>
                            <template #header>
                                <q-item-section avatar style="min-width: 85px" class='gt-sm'><q-badge :color="methodColors[item.methods[0] ?? 'GET']" class="q-pa-sm text-weight-medium">{{ item.methods[0] ?? 'GET' }}</q-badge></q-item-section>
                                <q-item-section avatar class='lt-md'><q-badge :color="methodColors[item.methods[0] ?? 'GET']" class="q-pa-sm text-weight-medium">{{ item.methods[0] ?? 'GET' }}</q-badge></q-item-section>
                                <q-item-section side class="text-weight-medium gt-xs"><q-btn outline dense no-caps size='13px' color='grey' class='q-px-sm rounded-borders' @click.stop='copyToClipboard(item.path)'>{{ item.path }}</q-btn></q-item-section>
                                <q-item-section class="text-weight-medium text-overflow">{{ item.title }}</q-item-section>
                                <q-item-section avatar class="flex">
                                    <div>
                                        <q-btn v-if="item.table" flat color="primary" @click.stop dense icon="format_list_bulleted"><q-tooltip>Data Table --> Table/{{ item.shortName }}.ts</q-tooltip></q-btn>
                                        <q-btn v-if="item.roles.length > 0" flat :color="item.isAuth ? 'negative' : 'positive'" @click.stop='showRoles(item)' dense icon="perm_identity"><q-tooltip>View Roles</q-tooltip></q-btn>
                                        <q-btn v-if="item.isAuth" flat @click.stop color="negative" dense icon="admin_panel_settings"><q-tooltip>Authorization required.</q-tooltip></q-btn>
                                        <q-btn flat dense icon="code" @click.stop><q-tooltip>Api client method --> {{ item.shortName }}()</q-tooltip></q-btn>
                                    </div>
                                </q-item-section>
                                <q-item-section avatar class="gt-sm">{{ item.controllerResponseType }}</q-item-section>
                            </template>
                            <q-card>
                                <q-card-section>
                                    <!--Dev Mode-->
                                    <div v-if="devMode" class='q-mb-md'>
                                        <b>Open PHP Storm: </b>
                                        <a :href="getPhpStormPath(item)">{{ item.controller }}</a>
                                    </div>

                                    <!--Info-->
                                    <q-banner v-if='item.info' rounded class='bg-white q-mb-md' style='padding-top: 12px; padding-bottom: 12px'>
                                        <template v-slot:avatar><q-icon name="info" color="primary" size='md'></q-icon></template>
                                        {{ item.info }}
                                    </q-banner>

                                    <q-list class='rounded-borders sub-expand'>
                                        <!--Route Attributes-->
                                        <q-expansion-item v-if='isEmpty(item.routeAttr)' dense dense-toggle icon="code" label="Route Attributes" header-class='bg-white'>
                                            <q-card class='q-table--dense'>
                                                <table class='q-table'>
                                                    <tbody>
                                                    <tr v-for='(v,k) in item.routeAttr'>
                                                        <td class="text-left" style='width: 25%'>{{ k }}:</td>
                                                        <td class="text-left">
                                                            <div class='flex gap-10'><span v-for='i in renderRouteAttr(v)'>{{ i }}</span></div>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </q-card>
                                        </q-expansion-item>

                                        <!--Header Parameters-->
                                        <q-expansion-item v-if='isEmpty(item.header)' dense dense-toggle icon="code" label="Header Parameters" header-class='bg-white'>
                                            <q-card class='q-table--dense'>
                                                <table class='q-table'>
                                                    <tbody>
                                                    <tr v-for='(v,k) in item.header'>
                                                        <td class="text-left" style='width: 25%'>{{ k }}:</td><td class="text-left">{{ v }}</td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </q-card>
                                        </q-expansion-item>

                                        <!--Query Parameters-->
                                        <q-expansion-item v-if='isEmpty(item.query)' dense dense-toggle icon="code" label="Query Parameters" header-class='bg-white'>
                                            <q-card class='q-table--dense'>
                                                <table class='q-table'>
                                                    <tbody>
                                                    <tr v-for='(v,k) in item.query'>
                                                        <td class="text-left" style='width: 25%'>{{ k }}:</td>
                                                        <td class="text-left">
                                                            <div v-if='isObj(v)'><pre>{{ v }}</pre></div>
                                                            <div v-else-if='isArrStr(v)'><pre>{{ renderQueryParam(v) }}</pre></div>
                                                            <div v-else class='flex gap-10'>
                                                                <q-badge color='primary' outline class='q-px-sm q-py-xs' v-for='e in renderQueryParam(v)'>{{ e }}</q-badge>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </q-card>
                                        </q-expansion-item>

                                        <!--Body Parameters-->
                                        <q-expansion-item v-if='isEmpty(item.request)' dense dense-toggle icon="data_array" label="Body Parameters" header-class='bg-white'>
                                            <q-card class='q-table--dense'>
                                                <table class='q-table'>
                                                    <tbody>
                                                    <tr v-for='(v,k) in item.request'>
                                                        <td class="text-left" style='width: 25%'>{{ k }}:</td>
                                                        <td class="text-left">
                                                            <div v-if='isObj(v)'><pre>{{ v }}</pre></div>
                                                            <div v-else class='flex gap-10'>
                                                                <q-badge color='primary' outline class='q-px-sm q-py-xs' v-for='e in renderBodyParam(v)'>{{ e }}</q-badge>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </q-card>
                                        </q-expansion-item>

                                        <!--Response-->
                                        <q-expansion-item v-if='isEmpty(item.response)' dense dense-toggle icon="data_object" label="Response" header-class='bg-white text-positive'>
                                            <q-card class='q-table--dense'>
                                                <table class='q-table'>
                                                    <tbody>
                                                    <tr v-for='(v,k) in item.response'>
                                                        <td class="text-left" style='width: 25%'>{{ k }}:</td>
                                                        <td class="text-left"><pre>{{ v }}</pre></td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </q-card>
                                        </q-expansion-item>

                                        <!--Response Exception-->
                                        <q-expansion-item v-if='isEmpty(item.exception)' dense dense-toggle icon="bug_report" label="Response Exception" header-class='bg-white text-negative'>
                                            <q-card class='q-table--dense'>
                                                <table class='q-table'>
                                                    <tbody>
                                                    <tr v-for='(v,k) in item.exception'>
                                                        <td class="text-left" style='width: 25%'>{{ k }}:</td>
                                                        <td class="text-left"><pre>{{ v }}</pre></td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </q-card>
                                        </q-expansion-item>
                                    </q-list>
                                </q-card-section>
                            </q-card>
                        </q-expansion-item>
                    </q-list>
                </div>
            </div>
        </q-page-container>
    </q-layout>
</div>
<script src="//cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js"></script>
<script src="//cdn.jsdelivr.net/npm/quasar@2.14.1/dist/quasar.umd.prod.js"></script>
<script>
    const app = Vue.createApp({
        data() {
            return {
                leftDrawerOpen: false,
                devMode: <?php echo json_encode($this->bag->get('kernel.environment')); ?>,
                projectDir: '<?php echo $this->bag->get('kernel.project_dir'); ?>',
                baseUrl: '<?php echo $this->bag->get('api.thor.base_url'); ?>',
                methodColors: {
                    'GET': 'primary',
                    'DELETE': 'negative',
                    'POST': 'positive',
                    'PUT' : 'warning',
                },
                downloadLinks: {
                    'TypeScript': "<?php echo $this->router->generate('thor.download'); ?>"
                },
                docData: <?php echo json_encode(array_filter($data, static fn ($v, $k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_BOTH), JSON_THROW_ON_ERROR); ?>
            }
        },
        computed: {
            getSidebarTitles() {
                return Object.keys(this.docData);
            },
        },
        methods:{
            scrollToId(id) {
                window.scrollTo({
                    top: document.getElementById(id).getBoundingClientRect().top + window.pageYOffset - 60,
                    behavior: "smooth"
                });
            },
            getPhpStormPath(item) {
                return `phpstorm://open?file=${this.projectDir + item.controllerPath}; ?>&line=${item.controllerLine}`
            },
            viewInfo() {
                this.$q.dialog({
                    title: 'System Details',
                    html:true,
                    message: 'Base URL: ' + this.baseUrl
                })
            },
            toSlugify(str) {
                return str.replace(/^\s+|\s+$/g, '').toLowerCase().replace(/[^a-z0-9 -]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            },
            showRoles(item) {
                this.$q.dialog({
                    title: 'Required Roles',
                    html:true,
                    class: 'fakfa',
                    message: `<div class="flex gap-10">${item.roles.map(i => `<p class="q-badge flex inline items-center no-wrap q-badge--single-line bg-blue q-pa-sm q-mb-none text-weight-medium">${i}</p>`).join('')}</div>`
                })
            },
            copyToClipboard(text) {
                Quasar.copyToClipboard(text).then(() => {
                    this.$q.notify({
                        message: 'Copied to clipboard',
                        caption: text,
                        icon: 'content_copy',
                        position: 'bottom',
                        progress: true,
                        timeout: 1500
                    })
                })
            },
            isEmpty(item) {
                if (Array.isArray(item) && item.length > 0) {
                    return true;
                }

                if (typeof(item) == 'object' && Object.keys(item).length > 0) {
                    return true
                }

                return false;
            },
            isObj(item) {
                return typeof(item) == 'object'
            },
            isArrStr(item) {
                return typeof(item) == 'string' && item.startsWith('[') && item.endsWith(']');
            },
            renderRouteAttr(str) {
                if (typeof(str) == 'string') {
                    return str.replaceAll('|', ';').split(';').map(i => i.split('\\').pop())
                }

                return str;
            },
            renderQueryParam(str) {
                if (str.startsWith('[') && str.endsWith(']')) {
                    return str.replace(/^\[+/, '').replace(/\]+$/, '').split('|')
                }
                if (typeof(str) == 'string') {
                    str = str.split('|')
                }

                return str;
            },
            renderBodyParam(str) {
                if (typeof(str) == 'string') {
                    str = decodeURIComponent(str).replaceAll('|', ';').split(';')
                }

                return str;
            }
        }
    })
    app.use(Quasar)
    app.mount('#app')
</script>
</body>
</html>