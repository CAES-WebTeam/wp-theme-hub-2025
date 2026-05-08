(function () {
    if (typeof wp === 'undefined' || !wp.data || !wp.data.subscribe) {
        return;
    }

    const { subscribe, select, dispatch } = wp.data;
    const FIELD = 'pdf_generation_notice';
    const VALID_TYPES = ['error', 'warning', 'success', 'info'];

    let lastSeenTimestamp = null;
    let wasSaving = false;
    let initialChecked = false;

    function showAndClearNotice() {
        const editor = select('core/editor');
        if (!editor) return;

        const postId = editor.getCurrentPostId();
        if (!postId) return;

        const notice = editor.getEditedPostAttribute(FIELD);
        if (!notice || typeof notice !== 'object' || !notice.message) return;

        const ts = notice.time || 0;
        if (ts === lastSeenTimestamp) return;
        lastSeenTimestamp = ts;

        const type = VALID_TYPES.indexOf(notice.type) !== -1 ? notice.type : 'info';
        dispatch('core/notices').createNotice(type, notice.message, {
            isDismissible: true,
            id: 'pdf-generation-notice',
        });

        wp.apiFetch({
            path: '/wp/v2/publications/' + postId,
            method: 'POST',
            data: { [FIELD]: null },
        }).catch(function () { /* non-fatal */ });
    }

    subscribe(function () {
        const editor = select('core/editor');
        if (!editor || !editor.getCurrentPostId()) return;

        const isSaving = editor.isSavingPost() && !editor.isAutosavingPost();
        const justFinishedSaving = wasSaving && !isSaving;
        wasSaving = isSaving;

        if (!initialChecked) {
            initialChecked = true;
            showAndClearNotice();
            return;
        }

        if (justFinishedSaving) {
            showAndClearNotice();
        }
    });
})();
