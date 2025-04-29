const { registerPlugin } = wp.plugins;
const { PluginDocumentSettingPanel } = wp.editPost;
const { Button } = wp.components;
const el = wp.element.createElement;

if (window.pubReviewData) {
    const { url, nonce } = window.pubReviewData;
    const fullUrl = `${url}&_wpnonce=${nonce}`;

    const ReviewReplacePanel = () => {
        return el(
            PluginDocumentSettingPanel,
            {
                name: "replace-original-review",
                title: "Review Controls",
                className: "replace-original-review-panel"
            },
            el(
                Button,
                {
                    isPrimary: true,
                    onClick: () => {
                        if (confirm("Are you sure you want to replace the original with this reviewed version?")) {
                            window.location.href = fullUrl;
                        }
                    }
                },
                "Replace Original with Review"
            )
        );
    };

    registerPlugin('replace-original-review-plugin', {
        render: ReviewReplacePanel,
        icon: null
    });
}
