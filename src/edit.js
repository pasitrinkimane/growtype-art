import {__} from '@wordpress/i18n';

/**
 * WordPress components that create the necessary UI elements for the block
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-components/
 */
import {
    TextControl,
    TextareaControl,
    Panel,
    PanelBody,
    PanelRow,
    CustomSelectControl,
    SelectControl,
    ToggleControl,
    __experimentalNumberControl as NumberControl, RangeControl
} from '@wordpress/components';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import {
    PlainText, useBlockProps, InspectorControls, InspectorAdvancedControls
} from '@wordpress/block-editor';

import {useInstanceId} from '@wordpress/compose';

import {Icon, shortcode} from '@wordpress/icons';

import metadata from './block.json';

import {useState, useEffect} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @param {Object}   props               Properties passed to the function.
 * @param {Object}   props.attributes    Available block attributes.
 * @param {Function} props.setAttributes Function that updates individual attributes.
 *
 * @return {WPElement} Element to render.
 */
export default function Edit({attributes, setAttributes}) {
    const blockProps = useBlockProps();
    const instanceId = useInstanceId(Edit);
    const inputId = `blocks-shortcode-input-${instanceId}`;
    const [settings, setSettings] = useState([]);

    const requestSettings = async () => {
        const settings = await apiFetch({path: '/growtype-ai/v1/settings'});
        setSettings(settings);
        return;
    };

    function formatOptions(options) {
        let formattedOptions = [];
        Object.entries(options).map((option) => {
            formattedOptions.push({label: option[1], value: option[0]});
        })

        return formattedOptions;
    }

    useEffect(() => {
        requestSettings();
    }, []);

    const updateShortcode = (attribute_key, val, inputType) => {
        if (inputType === 'custom') {
            setAttributes({[attribute_key]: val.selectedItem.value})
        } else if (inputType === 'array') {
            setAttributes({[attribute_key]: val.toString()})
        } else {
            setAttributes({[attribute_key]: val})
        }

        let shortcodeTag = '[growtype_ai';
        Object.entries(attributes).map(function (element) {
            if (element[0] !== 'shortcode') {
                let propertyKey = element[0];
                let propertyValue = element[1];

                if (propertyKey === attribute_key) {
                    if (inputType === 'custom') {
                        propertyValue = val.selectedItem.value
                    } else {
                        propertyValue = val;
                    }
                }

                if (typeof propertyValue === "boolean") {
                    propertyValue = propertyValue ? 'true' : 'false'
                }

                if (propertyKey === 'visible_results_amount') {
                    propertyValue = propertyValue.toString()
                }

                if (propertyValue.length > 0) {
                    shortcodeTag += ' ' + propertyKey + '=' + '"' + propertyValue + '"'
                }
            }
        })

        shortcodeTag += ']';

        setAttributes({shortcode: shortcodeTag})
    };

    if (Object.entries(attributes).length === 0 || attributes.shortcode === '') {
        attributes.shortcode = '[growtype_ai]'
    }

    return (<div {...blockProps}>
        <InspectorControls key={'inspector'}>
            <Panel>
                <PanelBody
                    title={__('Main settings', 'growtype-ai')}
                    icon="admin-plugins"
                >

                </PanelBody>
            </Panel>
        </InspectorControls>

        <InspectorAdvancedControls>
            <TextControl
                label={__('Parent ID', 'growtype-ai')}
                onChange={(val) => updateShortcode('parent_id', val)}
                value={attributes.parent_id}
            />
        </InspectorAdvancedControls>

        <div {...useBlockProps({className: 'components-placeholder'})}>
            <label
                htmlFor={inputId}
                className="components-placeholder__label"
            >
                <Icon icon={shortcode}/>
                {__('Growtype AI shortcode')}
            </label>
            <PlainText
                className="blocks-shortcode__textarea"
                id={inputId}
                value={attributes.shortcode}
                aria-label={__('Shortcode text')}
                placeholder={__('Write shortcode here…')}
                onChange={(val) => setAttributes({shortcode: val})}
            />
        </div>
    </div>);
}
