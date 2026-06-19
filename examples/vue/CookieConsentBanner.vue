<script setup>
// Self-contained Vue 3 demo banner for Craft CMP.
// It only renders and calls the framework-agnostic core — swap the framework,
// keep the logic. Copy `cookie-consent-core.js` (see ../javascript) alongside this.
import { ref, reactive, onMounted, computed } from 'vue';
import { CookieConsentManager } from './cookie-consent-core.js';

const props = defineProps({ apiBase: { type: String, default: '' } });

const cc = new CookieConsentManager({ apiBase: props.apiBase });
const config = ref(null);
const open = ref(false);
const showPrefs = ref(false);
const selection = reactive({});

const categories = computed(() => config.value?.categories ?? []);
const links = computed(() => config.value?.links ?? []);

onMounted(async () => {
    config.value = await cc.loadConfig();
    cc.bootstrapConsentMode();                 // Consent Mode v2: default-denied → GA + scripts
    Object.assign(selection, cc.currentCategories());
    open.value = cc.needsConsent();
});

function acceptAll() { cc.acceptAll(); open.value = showPrefs.value = false; }
function rejectAll() { cc.rejectAll(); open.value = showPrefs.value = false; }
function savePrefs() { cc.savePreferences({ ...selection }); open.value = showPrefs.value = false; }
function manage() { Object.assign(selection, cc.currentCategories()); showPrefs.value = true; }

// expose manage() so a footer link can re-open preferences:
defineExpose({ manage });
</script>

<template>
    <section v-if="open && config" class="cc-banner" role="dialog" :aria-label="config.banner.title">
        <div>
            <h2>{{ config.banner.title }}</h2>
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div v-html="config.banner.body" />
        </div>
        <div class="cc-actions">
            <button @click="manage">{{ config.banner.managePrefsLabel }}</button>
            <button @click="rejectAll">{{ config.banner.rejectAllLabel }}</button>
            <button @click="acceptAll">{{ config.banner.acceptAllLabel }}</button>
        </div>
        <ul v-if="links.length" class="cc-links">
            <li v-for="(link, i) in links" :key="i">
                <a
                    :href="link.url"
                    :target="link.newTab ? '_blank' : null"
                    :rel="link.newTab ? 'noopener noreferrer' : null"
                >{{ link.label }}</a>
            </li>
        </ul>
    </section>

    <div v-if="showPrefs" class="cc-modal" role="dialog" aria-modal="true">
        <div class="cc-modal__box">
            <h2>{{ config.banner.managePrefsLabel }}</h2>
            <label v-for="cat in categories" :key="cat.handle" class="cc-row">
                <span>
                    <strong>{{ cat.label }}</strong>
                    <small>{{ cat.description }}</small>
                </span>
                <input
                    type="checkbox"
                    :disabled="cat.required"
                    :checked="cat.required ? true : !!selection[cat.handle]"
                    @change="selection[cat.handle] = $event.target.checked"
                >
            </label>
            <div class="cc-actions">
                <button @click="showPrefs = false">Cancel</button>
                <button @click="savePrefs">{{ config.banner.savePrefsLabel }}</button>
            </div>
        </div>
    </div>
</template>
