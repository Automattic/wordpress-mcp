/**
 * WordPress dependencies
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { Notice, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

// Import the extracted components
import SettingsTab from './SettingsTab';
import ToolsTab from './ToolsTab';
import ResourcesTab from './ResourcesTab';
import PromptsTab from './PromptsTab';

/**
 * Settings App Component
 */
export const SettingsApp = () => {
	// State for settings
        const [settings, setSettings] = useState({
                enabled: false,
                features_adapter_enabled: false,
                enable_create_tools: false,
                enable_update_tools: false,
                enable_delete_tools: false,
                enabled_tools: undefined,
        });

	// State for UI
	const [isSaving, setIsSaving] = useState(false);
	const [notice, setNotice] = useState(null);
	const [activeTab, setActiveTab] = useState('settings');

	// Ref for tracking pending save timeouts
	const saveTimeoutRef = useRef(null);

	// Load settings on the component mount.
	useEffect(() => {
		if (
			window.wordpressMcpSettings &&
			window.wordpressMcpSettings.settings
		) {
			const loaded = window.wordpressMcpSettings.settings;
			setSettings((prev) => ({
				...prev,
				...loaded,
				enable_create_tools:
					typeof loaded.enable_create_tools === 'boolean'
						? loaded.enable_create_tools
						: false,
				enable_update_tools:
					typeof loaded.enable_update_tools === 'boolean'
						? loaded.enable_update_tools
						: false,
                                enable_delete_tools:
                                        typeof loaded.enable_delete_tools === 'boolean'
                                                ? loaded.enable_delete_tools
                                                : false,
                                enabled_tools: Array.isArray( loaded.enabled_tools ) ? loaded.enabled_tools : undefined,
                        }));
		}
	}, []);

	// Clean up any pending timeouts on unmounting
	useEffect(() => {
		return () => {
			if (saveTimeoutRef.current) {
				clearTimeout(saveTimeoutRef.current);
			}
		};
	}, []);

	// Handle toggle changes
        const handleToggleChange = (key) => {
                const newValue = !settings[key];

		// Update settings state with the new value
		setSettings((prevSettings) => {
			const updatedSettings = {
				...prevSettings,
				[key]: newValue,
			};

			// If disabling MCP and currently on a restricted tab, switch to settings tab
			if (key === 'enabled' && !newValue && activeTab !== 'settings') {
				setActiveTab('settings');
			}

			// Clear any pending save timeout
			if (saveTimeoutRef.current) {
				clearTimeout(saveTimeoutRef.current);
			}

			// Automatically save settings after state is updated
			saveTimeoutRef.current = setTimeout(() => {
				handleSaveSettingsWithData(updatedSettings);
				saveTimeoutRef.current = null;
			}, 500);

			return updatedSettings;
                });
        };

        const handleToolToggle = (toolName) => {
                setSettings((prevSettings) => {
                        const current = Array.isArray(prevSettings.enabled_tools)
                                ? [...prevSettings.enabled_tools]
                                : [];

                        if (current.includes(toolName)) {
                                const updated = current.filter((t) => t !== toolName);
                                const updatedSettings = {
                                        ...prevSettings,
                                        enabled_tools: updated,
                                };
                                if (saveTimeoutRef.current) {
                                        clearTimeout(saveTimeoutRef.current);
                                }
                                saveTimeoutRef.current = setTimeout(() => {
                                        handleSaveSettingsWithData(updatedSettings);
                                        saveTimeoutRef.current = null;
                                }, 500);
                                return updatedSettings;
                        }

                        const updated = [...current, toolName];
                        const updatedSettings = {
                                ...prevSettings,
                                enabled_tools: updated,
                        };

                        if (saveTimeoutRef.current) {
                                clearTimeout(saveTimeoutRef.current);
                        }
                        saveTimeoutRef.current = setTimeout(() => {
                                handleSaveSettingsWithData(updatedSettings);
                                saveTimeoutRef.current = null;
                        }, 500);

                        return updatedSettings;
                });
        };

	// Save settings with specific data
	const handleSaveSettingsWithData = (settingsData) => {
		setIsSaving(true);
		setNotice(null);

		// Create form data for AJAX request
		const formData = new FormData();
		formData.append('action', 'wordpress_mcp_save_settings');
		formData.append('nonce', window.wordpressMcpSettings.nonce);
		formData.append('settings', JSON.stringify(settingsData));

		// Send AJAX request
		fetch(ajaxurl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		})
			.then((response) => response.json())
			.then((data) => {
				setIsSaving(false);
				if (data.success) {
					setNotice({
						status: 'success',
						message:
							data.data.message ||
							window.wordpressMcpSettings.strings.settingsSaved,
					});
				} else {
					setNotice({
						status: 'error',
						message:
							data.data.message ||
							window.wordpressMcpSettings.strings.settingsError,
					});
				}
			})
			.catch((error) => {
				setIsSaving(false);
				setNotice({
					status: 'error',
					message: window.wordpressMcpSettings.strings.settingsError,
				});
				console.error('Error saving settings:', error);
			});
	};

	// Handle save settings button click
	const handleSaveSettings = () => {
		handleSaveSettingsWithData(settings);
	};

	// Get localized strings
	const strings = window.wordpressMcpSettings
		? window.wordpressMcpSettings.strings
		: {};

	const tabs = [
		{
			name: 'settings',
			title: __('Settings', 'wordpress-mcp'),
			className: 'wordpress-mcp-settings-tab',
		},
		{
			name: 'tools',
			title: __('Tools', 'wordpress-mcp'),
			className: 'wordpress-mcp-tools-tab',
			disabled: !settings.enabled,
		},
		{
			name: 'resources',
			title: __('Resources', 'wordpress-mcp'),
			className: 'wordpress-mcp-resources-tab',
			disabled: !settings.enabled,
		},
		{
			name: 'prompts',
			title: __('Prompts', 'wordpress-mcp'),
			className: 'wordpress-mcp-prompts-tab',
			disabled: !settings.enabled,
		},
	];

	return (
		<div className="wordpress-mcp-settings">
			{notice && (
				<Notice
					status={notice.status}
					isDismissible={true}
					onRemove={() => setNotice(null)}
					className={`notice notice-${notice.status} is-dismissible`}
				>
					{notice.message}
				</Notice>
			)}

			<TabPanel
				className="wordpress-mcp-tabs"
				tabs={tabs}
				activeClass="is-active"
				initialTabName={activeTab}
				onSelect={(tabName) => {
					const tab = tabs.find((t) => t.name === tabName);
					if (!tab.disabled) {
						setActiveTab(tabName);
						return tabName;
					}
					return activeTab;
				}}
			>
				{(tab) => {
					if (tab.disabled) {
						return (
							<div className="wordpress-mcp-disabled-tab-notice">
								<p>
									{__(
										'This feature is only available when MCP functionality is enabled.',
										'wordpress-mcp'
									)}
								</p>
								<p>
									{__(
										'Please enable MCP in the Settings tab first.',
										'wordpress-mcp'
									)}
								</p>
							</div>
						);
					}

					switch (tab.name) {
						case 'settings':
							return (
								<SettingsTab
									settings={settings}
									onToggleChange={handleToggleChange}
									isSaving={isSaving}
									strings={strings}
								/>
							);
                                               case 'tools':
                                                        return (
                                                                <ToolsTab
                                                                        enabledTools={settings.enabled_tools}
                                                                        onToggleTool={handleToolToggle}
                                                                        mcpEnabled={settings.enabled}
                                                                />
                                                        );
						case 'resources':
							return <ResourcesTab />;
						case 'prompts':
							return <PromptsTab />;
						default:
							return null;
					}
				}}
			</TabPanel>
		</div>
	);
};
