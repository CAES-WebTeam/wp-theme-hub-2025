import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import './editor.scss';

const SECTIONS = [
    { attr: 'showAreasOfExpertise', label: 'Areas of expertise',  anchor: 'areas-of-expertise' },
    { attr: 'showAbout',            label: 'About',               anchor: 'about' },
    { attr: 'showEducation',        label: 'Education',           anchor: 'education' },
    { attr: 'showAwards',           label: 'Awards and honors',   anchor: 'awards-and-honors' },
    { attr: 'showCourses',          label: 'Courses',             anchor: 'courses' },
    { attr: 'showScholarlyWorks',   label: 'Scholarly works',     anchor: 'scholarly-works' },
];

export default function Edit({ attributes, setAttributes }) {
    const visibleSections = SECTIONS.filter((s) => attributes[s.attr] !== false);

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Visible Sections', 'caes-hub')}>
                    {SECTIONS.map((s) => (
                        <ToggleControl
                            key={s.attr}
                            label={s.label}
                            checked={attributes[s.attr] !== false}
                            onChange={(value) => setAttributes({ [s.attr]: value })}
                        />
                    ))}
                </PanelBody>
            </InspectorControls>
            <nav {...useBlockProps({ className: 'person-section-nav' })}>
                {visibleSections.map((s) => (
                    <a key={s.attr} href={`#${s.anchor}`} className="person-section-nav__link">
                        {s.label}
                    </a>
                ))}
            </nav>
        </>
    );
}
