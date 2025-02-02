/**
 * waggo8.3
 * @copyright 2013-2024 CIEL, K.K., project waggo.
 * @license MIT
 **/

let WG8 = function () {
}
WG8.opts = function (opts) {
	return opts == null ? {} : opts
}
WG8.beforeLoad = function (jqs) {
}
WG8.afterLoad = function (jqs) {
}

WG8.remakeURI = function (uri, opts) {
	let k, p, a, t, r, q, u
	r = []
	k = {}
	p = (uri + "?").split("?")
	a = p[1].split("&")
	$.each(a, function (i, v) {
		t = v.split("=")
		pk = t.length > 0 ? decodeURIComponent(t[0].replace(/\+/g, '%20')) : ''
		pv = t.length > 1 ? decodeURIComponent(t[1].replace(/\+/g, '%20')) : ''
		k[pk] = t.length > 1 ? pv : ""
	})
	$.each(opts, function (pk, pv) {
		k[pk] = pv
	})
	$.each(k, function (pk, pv) {
		if (pk != null && pv != null) {
			let qk = encodeURIComponent(pk), qv = encodeURIComponent(pv)
			r.push(qk + (qv !== "" ? "=" + qv : ""))
		}
	})
	q = r.join("&")
	u = (r !== '') ? p[0] + "?" + q : p[0]

	return u
}

WG8.parse = function (jqs, jqx, u, opts) {
	opts = WG8.opts(opts)
	let d = {}, s = []

	try {
		let x = jqx.responseXML
		for (let el = x.documentElement; el != null; el = el.nextSibling) {
			if (el.nodeType === 1 && el.nodeName === 'result' && el.hasChildNodes()) {
				for (let el0 = el.firstChild; el0 != null; el0 = el0.nextSibling) {
					if (el0.nodeType === 1 && el0.nodeName === 'code' && el0.hasChildNodes()) {
						for (let el1 = el0.firstChild; el1 != null; el1 = el1.nextSibling)
							if (el1.nodeType === 3) d['code'] = el1.nodeValue
					}
					if (el0.nodeType === 1 && el0.nodeName === 'location' && el0.hasChildNodes()) {
						for (let el1 = el0.firstChild; el1 != null; el1 = el1.nextSibling)
							if (el1.nodeType === 3) d['location'] = el1.nodeValue
					}
					if (el0.nodeType === 1 && el0.nodeName === 'template' && el0.hasChildNodes()) {
						for (let el1 = el0.firstChild; el1 != null; el1 = el1.nextSibling)
							if (el1.nodeType === 4) d['template'] = el1.nodeValue
						if (el0.getAttribute('type') !== undefined) d['template.type'] = el0.getAttribute('type')
						if (el0.getAttribute('action') !== undefined) d['template.action'] = el0.getAttribute('action')
					}
					if (el0.nodeType === 1 && el0.nodeName === 'script' && el0.hasChildNodes()) {
						let src = '', ev = ''
						for (let el1 = el0.firstChild; el1 != null; el1 = el1.nextSibling) if (el1.nodeType === 3) src += el1.nodeValue
						ev = (el0.getAttribute('event') !== undefined) ? el0.getAttribute('event') : ''
						s.push({event: ev.toLowerCase(), src: src})
					}
				}
			}
		}
	} catch (e) {
		console.log(e.message)
	}

	// Location
	if (d['location'] !== undefined) {
		if (d.get['location'] === 'reload') window.location.reload()
		else WG8.get(jqs, d['location'], opts)
		return
	}

	// JavaScript EventTrigger
	s.forEach(function (o) {
		if (o.event === 'onpreload') eval(o.src)
	})

	if (d['template.action'] !== undefined)
		$(jqs).attr('data-wg-url', d['template.action'])
	else
		$(jqs).attr('data-wg-url', u)

	switch (d['template.type']) {
		case 'text/html':
			$(jqs).html(d['template'])
			break
		default:
			$(jqs).text(d['template'])
			break
	}

	// JavaScript EventTrigger
	s.forEach(function (o) {
		if (o.event === 'onloaded') eval(o.src)
	})
}

WG8.get = function (jqs, url, opts) {
	opts = WG8.opts(opts)
	$.ajax({
		url: url, method: 'GET', dataType: 'xml',
		beforeSend: function () {
			if (opts.beforeSend != null) opts.beforeSend(jqs)
			WG8.beforeLoad(jqs)
		},
		complete: function (jqx) {
			if (opts.beforeComplete != null) opts.beforeComplete(jqs)
			WG8.afterLoad(jqs)
			WG8.parse(jqs, jqx, url, opts)
			if (opts.afterComplete != null) opts.afterComplete(jqs)
		},
		error: function () {
			WG8.afterLoad(jqs)
			if (opts.onError != null) opts.onError(jqs)
			console.log('WG8.get failed, ' + url)
		}
	})
}

WG8.post = function (jqs, url, opts) {
	opts = WG8.opts(opts)
	let post = $(jqs).find('input,textarea,select').serialize()
	$.ajax({
		url: url, method: 'POST', data: post, dataType: 'xml',
		beforeSend: function () {
			if (opts.beforeSend != null) opts.beforeSend(jqs)
			WG8.beforeLoad(jqs)
		},
		complete: function (jqx) {
			if (opts.beforeComplete != null) opts.beforeComplete(jqs)
			WG8.afterLoad(jqs)
			WG8.parse(jqs, jqx, url, opts)
			if (opts.afterComplete != null) opts.afterComplete(jqs)
		},
		error: function () {
			WG8.afterLoad(jqs)
			if (opts.onError != null) opts.onError(jqs)
			console.log('WG8.post failed, ' + url)
		}
	})
}

WG8.reget = function (jqs, opts) {
	opts = WG8.opts(opts)
	$(jqs).each(function (i, q) {
		let t = $(q).closest('.wg-form')
		if (t.attr('data-wg-url') !== undefined && t.attr('data-wg-url') !== '') WG8.get(t, t.attr('data-wg-url'), opts)
	})
}

WG8.repost = function (jqs, opts) {
	opts = WG8.opts(opts)
	$(jqs).each(function (i, q) {
		let t = $(q).closest('.wg-form')
		if (t.attr('data-wg-url') !== undefined && t.attr('data-wg-url') !== '') WG8.post(t, t.attr('data-wg-url'), opts)
	})
}

WG8.reload = function (jqs, opts) {
	WG8.reget(jqs, opts)
}

WG8.closestForm = function (jqs) {
	return jqs.closest('.wg-form')
}
