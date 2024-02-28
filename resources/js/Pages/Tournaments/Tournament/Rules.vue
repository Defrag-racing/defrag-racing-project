<script setup>
    const props = defineProps({
        tournament: Object
    });
</script>

<template>
    <div>
        <div v-html="tournament.rules" class="wysiwyg-wrap2"></div>
    </div>
</template>

<style>
    .wysiwyg-wrap2 {
        text-align: left;
        color: white;
    }

    .wysiwyg-wrap2 h1 { 
        font-size: 4rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    .wysiwyg-wrap2 h2 { 
        font-size: 3rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    .wysiwyg-wrap2 h3 { 
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    .wysiwyg-wrap2 h4 { 
        font-size: 1rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    .wysiwyg-wrap2 h5 { 
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    .wysiwyg-wrap2 h6 { 
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }

    .wysiwyg-wrap2 a {
        text-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
        color: #3b82f6;
    }
    .wysiwyg-wrap2 a:hover {
        color: #2563eb;
    }

    .wysiwyg-wrap2 ul {
        padding-left: 1.25rem;
        list-style-position: outside;
        list-style-type: disc;
    }
    .wysiwyg-wrap2 ol {
        padding-left: 1.25rem;
        list-style-position: outside;
        list-style-type: decimal;
    }

    .wysiwyg-wrap2 .image-inline {
        display: inline-flex;
        max-width: 100%;
        align-items: flex-start;
    }
    .wysiwyg-wrap2 .image-inline picture {
        display: flex;
    }
    .wysiwyg-wrap2 .image-inline picture,
    .wysiwyg-wrap2 .image-inline img {
        flex-grow: 1;
        flex-shrink: 1;
        max-width: 100%;
    }

    .wysiwyg-wrap2 .image {
        display: table;
        clear: both;
        text-align: center;
        margin: 0.9em auto;
        min-width: 50px;
    }
    .wysiwyg-wrap2 .image.image_resized {
        max-width: 100%;
        display: block;
        box-sizing: border-box;
    }
    .wysiwyg-wrap2 .image.image_resized img {
        width: 100%;
    }
    .wysiwyg-wrap2 .image > figcaption {
        display: table-caption;
        caption-side: bottom;
        word-break: break-word;
        color: var(--ck-color-image-caption-text);
        background-color: var(--ck-color-image-caption-background);
        padding: 0.6em;
        font-size: 0.75em;
        outline-offset: -1px;
    }

    .wysiwyg-wrap2 pre {
        padding: 1rem;
        background-color: rgba(0, 0, 0, 0.212);
        color: white;
        border-radius: 0.5rem;
        overflow-x: auto;
        margin-top: 5px;
        margin-bottom: 5px;
    }

    .wysiwyg-wrap2 code {
        padding-left: 5px;
        padding-right: 5px;
        background-color: rgba(0, 0, 0, 0.212);
        color: white;
        border-radius: 0.5rem;
    }
</style>