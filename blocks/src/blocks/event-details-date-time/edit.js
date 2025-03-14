import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, TextControl, SelectControl } from '@wordpress/components';
import './editor.scss';

const Edit = ({ attributes, setAttributes }) => {
    return (
        <>
            <InspectorControls>
                <PanelBody title={__("Settings", "caes-hub")}>
                    <ToggleControl
                        label={__("Display heading", "caes-hub")}
                        checked={attributes.heading}
                        disabled={attributes.dateAsSnippet}
                        onChange={(val) => {
                            setAttributes({
                                heading: val,
                                dateAsSnippet: val ? attributes.dateAsSnippet : false,
                            });
                        }}
                    />
                    <ToggleControl
                        label={__("Display date", "caes-hub")}
                        checked={attributes.showDate}
                        disabled={attributes.dateAsSnippet}
                        onChange={(val) => setAttributes({ showDate: val })}
                    />
                    <ToggleControl
                        label={__("Display time", "caes-hub")}
                        checked={attributes.showTime}
                        disabled={attributes.dateAsSnippet}
                        onChange={(val) => setAttributes({ showTime: val })}
                    />
                    <ToggleControl
                        label={__("Display date as snippet", "caes-hub")}
                        checked={attributes.dateAsSnippet}
                        onChange={(val) => {
                            setAttributes({
                                dateAsSnippet: val,
                                heading: val ? true : attributes.heading,
                                showDate: val ? true : attributes.showDate,
                                showTime: val ? false : attributes.showTime,
                            });
                        }}
                    />
                </PanelBody>

                {/* Font Size Panel */}
                {attributes.heading && (
                    <PanelBody title={__("Heading Font Size", "caes-hub")}>
                        <TextControl
                            label={__("Font Size", "caes-hub")}
                            type="number"
                            value={attributes.headingFontSize || ""}
                            onChange={(val) => setAttributes({ headingFontSize: val })}
                        />
                        <SelectControl
                            label={__("Unit", "caes-hub")}
                            value={attributes.headingFontUnit}
                            options={[
                                { label: "px", value: "px" },
                                { label: "em", value: "em" },
                                { label: "rem", value: "rem" },
                                { label: "%", value: "%" },
                            ]}
                            onChange={(val) => setAttributes({ headingFontUnit: val })}
                        />
                    </PanelBody>
                )}
            </InspectorControls>

            <div {...useBlockProps()}>
                {(attributes.heading && !attributes.dateAsSnippet && !attributes.showDate && !attributes.showTime) ||
                (!attributes.heading && !attributes.dateAsSnippet && !attributes.showDate && !attributes.showTime) ? (
                    <p className="event-details-message">
                        <em>{__("Please turn on date, time, or date as snippet.", "caes-hub")}</em>
                    </p>
                ) : null}

                {attributes.heading && (attributes.showDate || attributes.showTime) && (
                    <h3
                        className="event-details-title"
                        style={{
                            fontSize: attributes.headingFontSize
                                ? `${attributes.headingFontSize}${attributes.headingFontUnit}`
                                : undefined,
                        }}
                    >
                        {attributes.dateAsSnippet
                            ? "January 15, 2024"
                            : `${attributes.showDate ? "Date" : ""}${attributes.showTime ? (attributes.showDate ? " & Time" : "Time") : ""}`}
                    </h3>
                )}

                {!attributes.dateAsSnippet && (attributes.showDate || attributes.showTime) && (
                    <div className="event-details-content">
                        {attributes.showDate && "January 15, 2024"}
                        {attributes.showDate && attributes.showTime && <br />}
                        {attributes.showTime && "10:00 AM - 2:00 PM"}
                    </div>
                )}
            </div>
        </>
    );
};

export default Edit;
