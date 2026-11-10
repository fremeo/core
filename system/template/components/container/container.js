(() => {
	const widths = (options) => {
		const parsed = {};
		for (const key of ['columns', 'xs', 'sm', 'md', 'lg']) {
			const value = options[key];
			if (value === '' && key !== 'columns') continue;
			if (typeof value !== 'string' || !/^(?:[1-9]|1[0-2])(?:\s*\+\s*(?:[1-9]|1[0-2]))*$/.test(value)) {
				throw new Error('Ungueltige Grid-Spalten (' + key + '). Beispiel: 8+4; Breiten von 1 bis 12.');
			}
			parsed[key] = value.split(/\s*\+\s*/).map(Number);
			if (parsed[key].length > 12) throw new Error('Ein Grid darf maximal 12 Spalten besitzen (' + key + ').');
		}
		const count = parsed.columns.length;
		if (Object.values(parsed).some((values) => values.length !== count)) throw new Error('Alle Grid-Aufteilungen muessen dieselbe Spaltenanzahl besitzen.');
		return { xs: parsed.xs || Array(count).fill(12), sm: parsed.sm, md: parsed.md || (parsed.xs || parsed.sm ? null : parsed.columns), lg: parsed.lg };
	};
	window.BuilderComponentEditors ||= {};
	window.BuilderComponentEditors['fremeo/core:container'] = {
		validate: widths,
		settings(section, options, bindControl) {
			if (!section) throw new Error('Grid-Einstellungs-TPL fehlt.');
			const select = section.querySelector('[data-grid-columns]');
			select.value = String(options.columns.split('+').length);
			const grid = widths(options);
			const inputs = {};
			const active = {};
			const previews = {};
			const draw = () => {
				let parsed;
				try {
					parsed = widths({ ...options, ...Object.fromEntries(Object.entries(inputs).map(([size, input]) => [size, input.value])) });
				} catch (error) {
					for (const preview of Object.values(previews)) preview.textContent = error.message;
					return;
				}
				let inherited;
				for (const size of ['xs', 'sm', 'md', 'lg']) {
					inherited = parsed[size] || inherited;
					const preview = previews[size];
					preview.replaceChildren();
					preview.style.opacity = active[size].checked ? '1' : '0.35';
					for (const width of inherited) {
						const cell = preview.ownerDocument.createElement('div');
						cell.style.cssText = 'flex:0 0 ' + (width / 12 * 100) + '%;height:28px;padding:2px;box-sizing:border-box';
						const fill = preview.ownerDocument.createElement('div');
						fill.style.cssText = 'height:100%;background:#55bfff;border:1px solid #fff';
						cell.append(fill);
						preview.append(cell);
					}
				}
			};
			let inherited;
			for (const size of ['xs', 'sm', 'md', 'lg']) {
				inherited = grid[size] || inherited;
				const view = section.querySelector('[data-grid-size="' + size + '"]');
				const preview = view.querySelector('[data-grid-preview]');
				previews[size] = preview;
				const inputLabel = view.querySelector('[data-grid-field="' + size + '"]');
				bindControl(inputLabel, { type: 'string', label: size }, options[size]);
				const input = inputLabel.querySelector('input');
				input.dataset.gridOption = size;
				input.placeholder = inherited.join('+');
				input.addEventListener('input', draw);
				inputs[size] = input;
				const activeKey = 'active' + size[0].toUpperCase() + size.slice(1);
				const activeLabel = view.querySelector('[data-grid-field="' + activeKey + '"]');
				bindControl(activeLabel, { type: 'boolean', label: 'Active' }, options[activeKey]);
				const checkbox = activeLabel.querySelector('input');
				checkbox.dataset.gridOption = activeKey;
				checkbox.addEventListener('change', draw);
				active[size] = checkbox;
			}
			select.addEventListener('change', () => {
				const count = Number(select.value);
				const columns = Array.from({ length: count }, (_, index) => Math.floor(12 / count) + (index < 12 % count ? 1 : 0)).join('+');
				for (const input of Object.values(inputs)) input.value = columns;
				options = { ...options, columns };
				draw();
			});
			draw();
			return {
				read: () => ({
					columns: options.columns,
					...Object.fromEntries(Object.entries(inputs).map(([size, input]) => [size, input.value])),
					...Object.fromEntries(Object.values(active).map((input) => [input.dataset.gridOption, input.checked])),
				}),
			};
		},
		create(doc, options, id) {
			widths(options);
			const component = doc.createElement('div');
			component.id = id;
			component.className = 'component component-container';
			component.dataset.componentModule = 'fremeo/core';
			const row = doc.createElement('div');
			row.className = 'builder-container row g-3 mx-0';
			row.dataset.builderChildren = '';
			component.append(row);
			return component;
		},
		layout(node, options) {
			const grid = widths(options);
			const row = node.querySelector('[data-builder-children]');
			row.className = 'builder-container row g-3 mx-0 ' + ['xs', 'sm', 'md', 'lg'].map((size) =>
				'd-' + (size === 'xs' ? '' : size + '-') + (options['active' + size[0].toUpperCase() + size.slice(1)] ? 'flex' : 'none')).join(' ');
			const cells = [...row.children].filter((cell) => cell.classList.contains('col'));
			for (let index = cells.length - 1; index >= grid.xs.length; index--) {
				if (!cells[index].querySelector('[data-builder-node]')) cells.splice(index, 1)[0].remove();
			}
			while (cells.length < grid.xs.length) {
				const cell = row.ownerDocument.createElement('div');
				cell.className = 'col';
				row.append(cell);
				cells.push(cell);
			}
			cells.forEach((cell, index) => {
				const children = [...cell.children].filter((item) => item.hasAttribute('data-builder-node'));
				const viewports = children.length ? children.flatMap((child) => {
					const general = JSON.parse(child.dataset.builderGeneral || '{}');
					return general.Active === false || child.dataset.builderLanguageVisible === '0' ? [] : general.Viewports || ['XS', 'SM', 'MD', 'LG'];
				}) : ['XS', 'SM', 'MD', 'LG'];
				cell.hidden = children.length > 0 && !viewports.length;
				const visibility = ['XS', 'SM', 'MD', 'LG'].map((size, position) => 'd' + (position ? '-' + size.toLowerCase() : '')
					+ (viewports.includes(size) ? '-block' : '-none'));
				cell.className = 'col ' + Object.entries(grid).flatMap(([size, values]) => values
					? ['col-' + (size === 'xs' ? '' : size + '-') + values[index % values.length]] : []).concat(visibility).join(' ');
			});
		},
	};
})();
