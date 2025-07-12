// Components
import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
    InnerBlocks,
    BlockControls
} from '@wordpress/block-editor';
import {
    PanelBody,
    FormTokenField,
    __experimentalNumberControl as NumberControl,
    Spinner,
    ToolbarGroup,
    ToolbarButton,
    RangeControl,
    __experimentalToggleGroupControl as ToggleGroupControl,
    __experimentalToggleGroupControlOption as ToggleGroupControlOption,
    SelectControl
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

// Import editor CSS
import './editor.scss';

// Preset spacing classes and labels
const SPACING_CLASSES = [
    '', // None (no gap)
    '--wp--preset--spacing--20',
    '--wp--preset--spacing--30',
    '--wp--preset--spacing--40',
    '--wp--preset--spacing--50',
    '--wp--preset--spacing--60',
    '--wp--preset--spacing--70',
    '--wp--preset--spacing--80',
];

const SPACING_LABELS = [
    'None (no gap)',
    '2X-Small',
    'X-Small',
    'Small',
    'Medium',
    'Large',
    'X-Large',
    '2X-Large',
];

export default function Edit({ attributes, setAttributes }) {
    const {
        userIds = [],
        feedType = 'hand-picked',
        numberOfUsers = 5,
        customGapStep = 0,
        displayLayout = 'list',
        columns = 3,
        gridItemPosition = 'manual',
        gridAutoColumnWidth = 12,
        gridAutoColumnUnit = 'rem'
    } = attributes;

    const [availableUsers, setAvailableUsers] = useState([]);
    const [selectedUsers, setSelectedUsers] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [searchTimeout, setSearchTimeout] = useState(null);

    // Update selectedUsers when userIds change (from saved attributes)
    useEffect(() => {
        if (userIds.length === 0) {
            setSelectedUsers([]);
            return;
        }

        // Only fetch if we don't already have these users stored
        const missingIds = userIds.filter(id => !selectedUsers.find(u => u.id === id));

        if (missingIds.length > 0) {
            apiFetch({
                path: `/wp/v2/users?include=${missingIds.join(',')}&_fields=id,name,slug`
            })
                .then((users) => {
                    const userList = users.map((user) => ({
                        id: user.id,
                        label: user.name || `User #${user.id}`,
                    }));
                    setSelectedUsers(prev => {
                        // Merge new users with existing, avoid duplicates
                        const existing = prev.filter(u => !missingIds.includes(u.id));
                        return [...existing, ...userList];
                    });
                })
                .catch((error) => {
                    console.error('Error fetching selected users:', error);
                });
        }
    }, [userIds]);

    // Search users with debounce
    const searchUsers = (term) => {
        if (term.length < 3) {
            setAvailableUsers([]);
            return;
        }

        setIsLoading(true);

        apiFetch({
            path: `/wp/v2/users?search=${encodeURIComponent(term)}&per_page=20&_fields=id,name,slug`
        })
            .then((users) => {
                const userList = users.map((user) => ({
                    id: user.id,
                    label: user.name || `User #${user.id}`,
                }));
                setAvailableUsers(userList);
                setIsLoading(false);
            })
            .catch(() => {
                setAvailableUsers([]);
                setIsLoading(false);
            });
    };

    // Handle search with debounce
    const handleSearch = (term) => {
        setSearchTerm(term);

        // Clear existing timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        // Set new timeout
        const newTimeout = setTimeout(() => {
            searchUsers(term);
        }, 300); // 300ms debounce

        setSearchTimeout(newTimeout);
    };

    // Cleanup timeout on unmount
    useEffect(() => {
        return () => {
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
        };
    }, [searchTimeout]);

    // Combine selected users and search results for suggestions, avoid duplicates
    const allUsers = [...selectedUsers, ...availableUsers.filter(u => !selectedUsers.find(s => s.id === u.id))];
    const selectedUserLabels = selectedUsers.filter(u => userIds.includes(u.id)).map(u => u.label);
    const userSuggestions = allUsers.map((u) => u.label);

    // Updated template to match your design
    const DEFAULT_TEMPLATE = [
        ['core/group', {
            className: 'caes-hub-user-feed-item',
            style: {
                spacing: {
                    blockGap: '0'
                },
                shadow: 'var:preset|shadow|small'
            },
            layout: {
                type: 'default'
            }
        }, [
            ['caes-hub/user-image', {
                aspectRatio: '3/4'
            }],
            ['caes-hub/user-image', {
                mobileVersion: true,
                aspectRatio: '3/4'
            }],
            ['core/group', {
                style: {
                    spacing: {
                        blockGap: 'var:preset|spacing|20',
                        padding: {
                            top: 'var:preset|spacing|50',
                            bottom: 'var:preset|spacing|60',
                            left: 'var:preset|spacing|50',
                            right: 'var:preset|spacing|50'
                        }
                    }
                },
                layout: {
                    type: 'default'
                }
            }, [
                ['core/group', {
                    style: {
                        typography: {
                            fontStyle: 'light',
                            fontWeight: '300',
                            textTransform: 'none'
                        }
                    },
                    fontFamily: 'oswald',
                    layout: {
                        type: 'default'
                    }
                }, [
                    ['caes-hub/user-name', {
                        element: 'h2',
                        linkToProfile: true,
                        style: {
                            typography: {
                                textAlign: 'center'
                            }
                        }
                    }]
                ]],
                ['caes-hub/user-position', {
                    style: {
                        typography: {
                            lineHeight: '1.2',
                            fontSize: '1.2rem',
                            textAlign: 'center'
                        }
                    }
                }]
            ]]
        ]]
    ];

    // Generate class names based on attributes
    const baseClass = displayLayout === 'grid'
        ? `user-feed-grid columns-${columns}`
        : 'user-feed-list';

    const spacingClass = customGapStep > 0
        ? `gap-${SPACING_CLASSES[customGapStep].replace(/^--/, '').replace(/--/g, '-')}`
        : '';

    const combinedClassName = `${baseClass} ${spacingClass}`.trim();

    const blockProps = useBlockProps();

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('User Feed Settings', 'user-feed')}>
                    <FormTokenField
                        label={__('Select Users', 'user-feed')}
                        value={selectedUserLabels}
                        suggestions={userSuggestions}
                        onInputChange={handleSearch}
                        onChange={(selectedLabels) => {
                            const selectedIds = selectedLabels
                                .map((label) => {
                                    const match = allUsers.find((u) => u.label === label);
                                    return match ? match.id : null;
                                })
                                .filter((id) => id !== null);
                            setAttributes({ userIds: selectedIds });
                        }}
                        help={
                            searchTerm.length > 0 && searchTerm.length < 3
                                ? __('Type at least 3 characters to search users', 'user-feed')
                                : isLoading
                                    ? __('Searching users...', 'user-feed')
                                    : availableUsers.length === 0 && searchTerm.length >= 3
                                        ? __('No users found. Try a different search term.', 'user-feed')
                                        : __('Search for users to add them to the feed', 'user-feed')
                        }
                    />
                    {isLoading && <Spinner />}
                </PanelBody>

                <PanelBody title={__('Layout Settings', 'user-feed')} initialOpen={false}>
                    {displayLayout === 'grid' && (
                        <>
                            <ToggleGroupControl
                                label={__('Grid Item Position', 'user-feed')}
                                value={gridItemPosition}
                                onChange={(value) => setAttributes({ gridItemPosition: value })}
                                isBlock
                            >
                                <ToggleGroupControlOption value="auto" label="Automatic" />
                                <ToggleGroupControlOption value="manual" label="Manual" />
                            </ToggleGroupControl>

                            {gridItemPosition === 'auto' && (
                                <>
                                    <NumberControl
                                        label={__('Auto Column Width', 'user-feed')}
                                        value={gridAutoColumnWidth}
                                        onChange={(value) => setAttributes({ gridAutoColumnWidth: parseFloat(value) })}
                                        min={1}
                                        step={1}
                                    />
                                    <SelectControl
                                        label={__('Auto Column Unit', 'user-feed')}
                                        value={gridAutoColumnUnit}
                                        onChange={(value) => setAttributes({ gridAutoColumnUnit: value })}
                                        options={[
                                            { value: 'rem', label: 'rem' },
                                            { value: 'px', label: 'px' },
                                            { value: '%', label: '%' },
                                        ]}
                                    />
                                </>
                            )}

                            {gridItemPosition === 'manual' && (
                                <RangeControl
                                    label={__('Number of Columns', 'user-feed')}
                                    value={columns}
                                    onChange={(value) => setAttributes({ columns: value })}
                                    min={1}
                                    max={16}
                                />
                            )}

                            <RangeControl
                                label={__('Gap between items', 'user-feed')}
                                value={customGapStep}
                                onChange={(value) => setAttributes({ customGapStep: value })}
                                min={0}
                                max={SPACING_CLASSES.length - 1}
                                step={1}
                                help={SPACING_LABELS[customGapStep] ? SPACING_LABELS[customGapStep] : 'No gap'}
                            />
                        </>
                    )}

                    {displayLayout === 'list' && (
                        <p>{__('List layout selected. Use grid layout for additional spacing options.', 'user-feed')}</p>
                    )}
                </PanelBody>
            </InspectorControls>

            <BlockControls>
                <ToolbarGroup>
                    <ToolbarButton
                        icon="list-view"
                        label={__('List View', 'user-feed')}
                        isPressed={displayLayout === 'list'}
                        onClick={() => setAttributes({ displayLayout: 'list' })}
                    />
                    <ToolbarButton
                        icon="grid-view"
                        label={__('Grid View', 'user-feed')}
                        isPressed={displayLayout === 'grid'}
                        onClick={() => setAttributes({ displayLayout: 'grid' })}
                    />
                </ToolbarGroup>
            </BlockControls>

            <div {...blockProps}>
                <div className={combinedClassName}>
                    {(!userIds || userIds.length === 0) && (
                        <p className="user-feed-empty">
                            {__('Please select one or more users from the sidebar.', 'user-feed')}
                        </p>
                    )}

                    {userIds && userIds.length > 0 && (
                        <InnerBlocks
                            template={DEFAULT_TEMPLATE}
                            templateLock={false}
                            renderAppender={InnerBlocks.ButtonBlockAppender}
                        />
                    )}
                </div>
            </div>
        </>
    );
}