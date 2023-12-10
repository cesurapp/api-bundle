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
</head>
<body>
<div id="app" class="bg-grey-2">
    <q-layout view="hHh lpR fFf">
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

        <q-drawer show-if-above v-model="leftDrawerOpen" side="left" class="bg-grey-2 q-pa-md">
            <q-list bordered separator class="rounded-borders">
                <q-item class="bg-white" :href="'#' + toSlugify(title)" clickable v-ripple v-for="title in getSidebarTitles"><q-item-section>{{ title }}</q-item-section></q-item>
            </q-list>
        </q-drawer>

        <q-page-container>
            <div class="q-px-md">
                <div :key="title" v-for="(stack, title) in docData">
                    <h5 :id="toSlugify(title)" class="text-h6 text-weight-regular q-mx-none q-mb-sm q-mt-md header">{{ title }}</h5>
                    <q-list bordered class="rounded-borders">
                        <q-expansion-item group="items" :key="title + index" v-for="(item, index) in stack" header-class="bg-white">
                            <template #header>
                                <q-item-section avatar style="min-width: 85px"><q-badge color="blue" class="q-pa-sm text-weight-medium">{{ item.methods[0] ?? 'GET' }}</q-badge></q-item-section>
                                <q-item-section side class="text-weight-medium"><span class="bordered rounded-borders q-py-xs q-px-sm">{{ item.path }}</span></q-item-section>
                                <q-item-section class="text-weight-medium">{{ item.title }}</q-item-section>
                                <q-item-section avatar class="flex">
                                    <div>
                                        <q-btn v-if="item.table" flat color="primary" dense icon="format_list_bulleted"><q-tooltip>Data Table -> Table/{{ item.shortName }}.ts</q-tooltip></q-btn>
                                        <q-btn v-if="item.roles.count > 0" flat color="negative" dense icon="perm_identity"><q-tooltip>View Roles</q-tooltip></q-btn>
                                        <q-btn v-if="item.isAuth" flat color="negative" dense icon="admin_panel_settings"><q-tooltip>Authorization required.</q-tooltip></q-btn>
                                        <q-btn flat dense icon="code"><q-tooltip>Api client method --> {{ item.shortName }}()</q-tooltip></q-btn>
                                    </div>
                                </q-item-section>
                                <q-item-section avatar>{{ item.controllerResponseType }}</q-item-section>
                            </template>
                            <q-card>
                                <q-card-section>
                                    <!--Dev Mode-->
                                    <div v-if="devMode">
                                        <b>Controller: </b>
                                        <a :href="getPhpStormPath(item)">{{ item.controller }}</a>
                                    </div>


                                </q-card-section>
                            </q-card>
                        </q-expansion-item>
                    </q-list>
                </div>
            </div>
        </q-page-container>
    </q-layout>
</div>

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
</style>
<script src="//cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js"></script>
<script src="//cdn.jsdelivr.net/npm/quasar@2.14.1/dist/quasar.umd.prod.js"></script>
<script>
    const app = Vue.createApp({
        data() {
            return {
                leftDrawerOpen: false,
                projectDir: '<?php echo $this->bag->get('kernel.project_dir'); ?>',
                baseUrl: '<?php echo $this->bag->get('api.thor.base_url'); ?>',
                devMode: <?php echo json_encode($this->bag->get('kernel.environment')); ?>,
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
                str = str.replace(/^\s+|\s+$/g, ''); // trim leading/trailing white space
                str = str.toLowerCase(); // convert string to lowercase
                str = str.replace(/[^a-z0-9 -]/g, '') // remove any non-alphanumeric characters
                    .replace(/\s+/g, '-') // replace spaces with hyphens
                    .replace(/-+/g, '-'); // remove consecutive hyphens
                return str;
            }
        }
    })
    app.use(Quasar)
    app.mount('#app')
</script>
</body>
</html>