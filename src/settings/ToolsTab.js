/**
 * WordPress dependencies
 */
import { Card, CardHeader, CardBody, Spinner, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Tools Tab Component
 */
const ToolsTab = ( { enabledTools, onToggleTool, mcpEnabled } ) => {
        const [ tools, setTools ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		const fetchTools = async () => {
			try {
				setLoading( true );
                                const response = await apiFetch( {
                                        path: '/wp/v2/wpmcp',
                                        method: 'POST',
                                        data: {
                                                jsonrpc: '2.0',
                                                method: 'tools/list',
                                                params: { all: true },
                                        },
                                } );

				if ( response && response.tools ) {
					setTools( response.tools );
				} else {
					setError(
						__( 'Failed to load tools data', 'wordpress-mcp' )
					);
				}
			} catch ( err ) {
				setError(
					__( 'Error loading tools: ', 'wordpress-mcp' ) + err.message
				);
			} finally {
				setLoading( false );
			}
		};

		fetchTools();
	}, [] );

	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'Registered Tools', 'wordpress-mcp' ) }</h2>
			</CardHeader>
			<CardBody>
				<p>
					{ __(
						'List of all registered tools in the system.',
						'wordpress-mcp'
					) }
				</p>

				{ loading ? (
					<div className="wordpress-mcp-loading">
						<Spinner />
						<p>{ __( 'Loading tools...', 'wordpress-mcp' ) }</p>
					</div>
				) : error ? (
					<div className="wordpress-mcp-error">
						<p>{ error }</p>
					</div>
				) : tools.length === 0 ? (
					<p>
						{ __(
							'No tools are currently registered.',
							'wordpress-mcp'
						) }
					</p>
				) : (
                                        <table className="wordpress-mcp-table">
                                                <thead>
                                                <tr>
                                                        <th>{ __( 'Name', 'wordpress-mcp' ) }</th>
                                                        <th>{ __( 'Description', 'wordpress-mcp' ) }</th>
                                                        <th>{ __( 'Functionality Type', 'wordpress-mcp' ) }</th>
                                                        <th>{ __( 'Enabled', 'wordpress-mcp' ) }</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                { tools.map( ( tool ) => (
                                                        <tr key={ tool.name }>
                                                                <td>
                                                                        <strong>{ tool.name }</strong>
                                                                </td>
                                                                <td>{ tool.description }</td>
                                                                <td>{ tool.type }</td>
                                                                <td>
                                                                        <ToggleControl
                                                                                checked={ Array.isArray( enabledTools ) ? enabledTools.includes( tool.name ) : true }
                                                                                onChange={ () => onToggleTool( tool.name ) }
                                                                                disabled={ ! mcpEnabled }
                                                                        />
                                                                </td>
                                                        </tr>
                                                ) ) }
                                                </tbody>
                                        </table>
                                ) }
                        </CardBody>
                </Card>
        );
};

export default ToolsTab;
