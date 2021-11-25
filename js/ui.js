/*
 * ================================================================================
 * Dynamic iPXE image generator
 *
 * Copyright (C) 2012-2021 Francois Lacroix. All Rights Reserved.
 * Website: http://ipxe.org, https://github.com/xbgmsharp/ipxe-buildweb
 * License: GNU General Public License version 3 or later; see LICENSE.txt
 * ================================================================================
 */
$(document).ready(function() {

        var roms = []; /* Global Object for roms ID validation */

        $.getJSON("gitversion.php", null, function(data) {
                //alert(data[0]);
                var git = '<p><h2 class="wizard-header">Generating iPXE build image version ' + data[0] + '</h2></p>';
                $("#gitabbrev").html(git);
                var options = "<option value='master' selected>master</option>";
                for (var i = 0; i < data.length; i++) {
                        //alert(data[i]);
                        options += '<option value="' + data[i] + '">' + data[i] + '</option>';
                }
                $("#gitrevision").html(options);
        })

        $.getJSON("nics.php", null, function(listnics) {
                //alert(listnics.length);
                var options = '<option value="all" selected>all-drivers</option>\n<option value="undionly">undionly</option>\n<option value="undi">undi</option>';
                for (var i = 0; i < listnics.length; i++) {
                        //alert(listnics[i].device_name);
                        //alert(listnics[i].ipxe_name);
                        options += '<option value="' + listnics[i].ipxe_name + '">' + listnics[i].ipxe_name + '</option>';
                        if (listnics[i].device_id != null && listnics[i].vendor_id != null) {
                            roms.push({device_id: listnics[i].device_id, vendor_id: listnics[i].vendor_id});
                        }
                }
                $("#nics").html(options);
        })

        $.getJSON("options.php", null, function(custom) {
                //alert(custom.length);

                // List of subtitle of options	
                var subtitle = new Object;
                subtitle._CMD = 'Command-line commands to include:';
                subtitle.NET_PROTO = 'Network protocols:';
                subtitle.IMAGE = 'Image types:';
                subtitle.PXE_ = 'PXE support:';
                subtitle.COM = 'Serial options:';
                subtitle.DOWNLOAD_PROTO = 'Download protocols:';
                subtitle.SANBOOT_PROTO = 'SAN boot protocols:';
                subtitle.CRYPTO_80211 = 'Wireless Interface Options:';
                subtitle.CONSOLE = 'Console options:';
                subtitle.ISA = 'ISA options:';
                subtitle.PCIAPI = 'PCIAPI options:';
                subtitle.COLOR = 'Color options:';
                subtitle.DNS = 'Name resolution modules:';
                subtitle.VMWARE = 'VMware options:'
                subtitle.GDB = 'Debugger options:'
                subtitle.NONPNP = 'ROM-specific options:';
                subtitle.ERRMSG = 'Error message tables to include:';
                subtitle.BANNER = 'Timer configuration:';
                subtitle.NETDEV = 'Obscure configuration options:';
                subtitle.PRODUCT = 'Branding options:';
                subtitle.DHCP = 'DHCP timeout parameters:';
                subtitle.PXEBS = 'PXE Boot Server timeout parameters:';
                subtitle.USB = 'USB configuration:';
                subtitle.HTTP = 'HTTP extensions:';
                subtitle.VNIC = 'Virtual network devices:';

                var listoptions = '';
                var previous;
                var desc;
                for (var i = 0; i < custom.length; i++) {
                        //alert(custom[i].name);
                        //alert(custom[i].description);
                        for (var y in subtitle) 
                        {
                                var regexp = new RegExp(y);
                                var match = regexp.exec(custom[i].name);
                                //if (custom[i].name.indexOf("_CMD", custom[i].name.length - 4) !== -1)
                                /*if (custom[i].name === "PXE_CMD")
                                {
                                        console.log('['+ y + '] vs [' + custom[i].name + '] match:' + match + ' && previous:' + previous);
                                }*/
                                if (previous == y && match == y)
                                {
                                        break;
                                }
                                else if (match != null && previous != y)
                                {
                                        listoptions += '<h3 class="wizard-option">'+ subtitle[y] + '</h3>'
                                        previous = y;
                                        break;
                                }
                        }
                        if (custom[i].type == "define" && (custom[i].name.indexOf("BANNER") !== -1)) {
                                desc = custom[i].description;
                                if (custom[i].name === custom[i].description) { desc = ""; }
                                listoptions += '<label for="' + custom[i].name + '">' + custom[i].name + ': <input type="text" size="6" placeholder="' + custom[i].description.replace('"', '') + '" value="' + custom[i].description.replace('"', '') + '" name="' + custom[i].file + '/' + custom[i].name +'" /> Default: ' + desc + '</label><br/><br/>';
                        } else if (custom[i].type == "define" && (custom[i].description.indexOf("0x") !== -1)) {
                                desc = custom[i].description;
                                if (custom[i].name === custom[i].description) { desc = ""; }
                                listoptions += '<label for="' + custom[i].name + '">' + custom[i].name + ': <input type="text" size="6" placeholder="' + custom[i].description.replace('"', '') + '" value="' + custom[i].description.replace('"', '') + '" name="' + custom[i].file + '/' + custom[i].name +'" /> Default: ' + desc + '</label><br/><br/>';
			} else if (custom[i].type == "define") {
                                custom[i].href_help_name = '<a class="help_buildcfg" href="http://www.ipxe.org/buildcfg/' + custom[i].name + '" target="_blank">' + custom[i].name + '</a>';
                                listoptions += '<label for="' + custom[i].name + '"><input type="checkbox" value="1" name="' + custom[i].file + '/' + custom[i].name + '" checked/>' + custom[i].href_help_name + ', ' + custom[i].description + '</label><br/><br/>';
                        } else if (custom[i].type == "undef") {
                                custom[i].href_help_name = '<a class="help_buildcfg" href="http://www.ipxe.org/buildcfg/' + custom[i].name + '" target="_blank">' + custom[i].name + '</a>';
                                listoptions += '<label for="' + custom[i].name + '"><input type="checkbox" value="0" name="' + custom[i].file + '/' + custom[i].name + '" />' + custom[i].href_help_name + ', ' + custom[i].description + '</label><br/><br/>';
                        } else if (custom[i].type == "input") {
                                desc = custom[i].description;
                                if (custom[i].name === custom[i].description) { desc = ""; }
				if (custom[i].name.indexOf("PRODUCT") !== -1)
	                                listoptions += '<label for="' + custom[i].name + '">' + custom[i].name + ': <input type="text" size="50" placeholder="' + custom[i].value.replace('"', '') + '" value="' + custom[i].value.replace('"', '') + '" name="' + custom[i].file + '/' + custom[i].name +'" /> ' + desc + '</label><br/><br/>';
				else
	                                listoptions += '<label for="' + custom[i].name + '">' + custom[i].name + ': <input type="text" size="6" placeholder="' + custom[i].value.replace('"', '') + '" value="' + custom[i].value.replace('"', '') + '" name="' + custom[i].file + '/' + custom[i].name +'" /> ' + desc + '</label><br/><br/>';
                        } else { alert("we have an issue"); }
                }
                $("#options").html(listoptions);
        })

        /* Reset from on reload */
        $("input[name=wizardtype]:first").prop('checked', true);
        $("#outputformatstd").prop('selectedIndex', 0);
        $("#outputformatadv").prop('selectedIndex', 0);

        $("#formtype").change(function(){
                var wizardtype = $('input:radio[name=wizardtype]:checked').val();
                //alert(wizardtype);
                if (wizardtype == "standard")
                {
                        $("#divstandard").css({'display': 'inline'});
                        $("#divadvanced").css({'display': 'none'});
                }
                else if (wizardtype == "advanced")
                {
                        $("#divstandard").css({'display': 'none'});
                        $("#divadvanced").css({'display': 'inline'});
                }
        });

        $("#gitrevision").change(function(){
                var gitversion = $("#gitrevision").val();
                var git = '<p><h2 class="wizard-header">Generating iPXE build image version ' + gitversion + '</h2></p>';
                $("#gitabbrev").html(git);
        });

        $("#outputformatstd").change(function(){
                var outputformat = $("#outputformatstd").val();
                //alert(outputformat);
                if (outputformat == "-")
                {
                        $("#embedded").css({'display': 'none'});
                        $("#debug").css({'display': 'none'});
                        $("#gitversion").css({'display': 'none'});
                        $("#build").css({'display': 'none'});
                }
                else
                {
                        $("#embedded").css({'display': 'inline'});
                        $("#debug").css({'display': 'inline'});
                        $("#gitversion").css({'display': 'inline'});
                        $("#build").css({'display': 'inline'});
                }
        });

        $("#outputformatadv").change(function(){
                var outputformat = $("#outputformatadv").val();
                //alert(outputformat);
                if (outputformat.indexOf("rom", outputformat.length - 3) !== -1)
                {	/* If a ROM */
                        $("#rom").css({'display': 'inline'});
                        $("#iface").css({'display': 'none'});
                        $("#config").css({'display': 'none'});
                        $("#embedded").css({'display': 'inline'});
                        $("#debug").css({'display': 'none'});
                        $("#gitversion").css({'display': 'inline'});
                        $("#build").css({'display': 'inline'});
                }
                else if (outputformat == "-" || outputformat == "--")
                {	/* If default */
                        $("#rom").css({'display': 'none'});
                        $("#iface").css({'display': 'none'});
                        $("#config").css({'display': 'none'});
                        $("#embedded").css({'display': 'none'});
                        $("#debug").css({'display': 'none'});
                        $("#gitversion").css({'display': 'none'});
                        $("#build").css({'display': 'none'});
                }
                else
                {
                        $("#rom").css({'display': 'none'});
                        $("#iface").css({'display': 'inline'});
                        $("#config").css({'display': 'inline'});
                        $("#embedded").css({'display': 'inline'});
                        $("#debug").css({'display': 'inline'});
                        $("#gitversion").css({'display': 'inline'});
                        $("#build").css({'display': 'inline'});
                }
        });

        /* Compose build.fcgi url */
        function buildcfg() {
                /* Get values from form */
                var wizard = $('input:radio[name=wizardtype]:checked').val();
                var bindir = "";
                var binary = "";
                var options = "";
                /* Get generic values from form */
                var debug = escape($("#setdebug").val());
                var revision = $("#gitrevision").val();
                var embed = escape($("#embed").val());
                if (embed == "#!ipxe") { embed = ""; }
                if (wizard == "standard")
                { 	/* get values from elements on the STD wizard */
                        bindir = $("#outputformatstd").val().split("/")[0]; 
                        binary = $("#outputformatstd").val().split("/")[1];
                }
                else if (wizard == "advanced")
                {	/* get values from elements on the ADV wizard */
                        bindir = $("#outputformatadv").val().split("/")[0]; 
                        binary = $("#outputformatadv").val().split("/")[1];
                        if (binary.indexOf("rom", binary.length - 3) !== -1)
                        {
                                /* Ensure device_id and vendor_id are valid */
                                var pci_vendor_code = $("#pci_vendor_code").val().toLowerCase();
                                var pci_device_code = $("#pci_device_code").val().toLowerCase();
                                /* using jQuery.grep as polyfill for older browsers without Array.prototype.filter */
                                var idx_nic = $.grep(roms, function(obj) {
                                        return obj.vendor_id == pci_vendor_code && obj.device_id == pci_device_code;
                                });
                                if (pci_vendor_code && pci_device_code && idx_nic && idx_nic.length > 0) {
                                    binary = $("#pci_vendor_code").val().toLowerCase() + $("#pci_device_code").val().toLowerCase() + "." + binary;
                                } else {
                                    $("#pci_roms_id_error").css({'display': 'inline'});
                                    $("#pci_roms_id_error").html("Invalid or unsupported pci_vendor_code or pci_device_code <br/>");
                                    return;
                                }
                        }
                        /* For all Checkbox in options div */
                        $("#options").find("input:checkbox").each(function(index) {
                                var name = $(this).prop("name");
                                var value = $(this).prop("checked") ? 1 : 0;
                                if ($(this).val() != value) {
                                        console.log( "Checkbox:" + index + ": " + name + " default: " + $(this).val() + " new: " + $(this).prop("checked") );
                                        options += name + ":=" + value + "&";
                                }
                        });
                        /* For all text field in options div */
                        $("#options").find("input:text").each(function(index) {
                                var name = $(this).prop("name");
                                var placeholder = $(this).prop("placeholder");
                                if ($(this).val() != placeholder) {
                                        console.log( "Text:" + index + ": " + name + " default: " + $(this).prop("placeholder") + " new: " + $(this).val());
                                        options += name + "=" + escape($(this).val()) + "&";
                                }
                        });
                }

                console.log('{ BINARY: ['+ binary +'], BINDIR: ['+ bindir +'], DEBUG: ['+ debug +'], REVISION: ['+ revision +'], EMBED: ['+ embed +'] , OPTIONS: ['+ options +']}');

                return 'build.fcgi?BINARY='+binary+'&BINDIR='+bindir+'&REVISION='+revision+'&DEBUG='+debug+'&EMBED.00script.ipxe='+embed+'&'+options;
        };

		/* Update fields with defaults from current URL */
		function loadcfg(delay) {
			/* if the form is not ready, wait a while */
			if ($("#gitrevision,#nics,#options").length != 3
				|| $("#gitrevision").html().trim().length == 0
				|| $("#nics").html().trim().length == 0
				|| $("#options").html().trim().length == 0
			) {
				if (typeof delay == "undefined") {
					delay = 0;
				}
				if (delay < 700) {
					delay = (delay*2)+100;
				} else if (delay < 3000) {
					delay += 100
				} else {
					delay = 3000
				}
				setTimeout(function(){loadcfg(delay);}, delay);
				return;
			}
			/* Parse querystring and generate args */
			var querystring = window.location.search;
			querystring = querystring.replace(/^\?+/, '');
			if (querystring == "") return;
			querystring = querystring.split('&');
			var args = {};
			querystring.forEach(function(item){
				var key = item.split('=', 1)[0];
				var value = item.substr(key.length + 1);
				key = key.replace(/:$/, '');
				args[key] = value;
			});
			/* Get values from args */
			//$('input:radio[name=wizardtype]#advanced').prop('checked', true);
			$('input:radio[name=wizardtype]#advanced').click();
			var bindir = (args['BINDIR'] > "") ? args['BINDIR'] : "";
			var binary = (args['BINARY'] > "") ? args['BINARY'] : "";
			var debug = (args['DEBUG'] > "") ? args['DEBUG'] : "";
			var revision = (args['REVISION'] > "") ? args['REVISION'] : "";
			var embed = (args['EMBED.00script.ipxe'] > "") ? args['EMBED.00script.ipxe'] : "";
			/* parse pci id for rom images */
			if (binary.indexOf("rom", binary.length - 3) !== -1)
			{
				var pci_id_code = binary.split('.', 1)[0];
				binary = binary.substr(pci_id_code.length + 1);
				if (pci_id_code.length != 8) {
					console.error("Unknown format for pci id: ", pci_id_code);
				} else {
					/* Ensure device_id and vendor_id are valid */
					var pci_vendor_code = pci_id_code.substr(0,4);
					var pci_device_code = pci_id_code.substr(4,4);
					$("#pci_vendor_code").val(pci_vendor_code).change();
					$("#pci_device_code").val(pci_device_code).change();
					/* using jQuery.grep as polyfill for older browsers without Array.prototype.filter */
					var idx_nic = $.grep(roms, function(obj) {
						return obj.vendor_id == pci_vendor_code && obj.device_id == pci_device_code;
					});
					if (!pci_vendor_code || !pci_device_code || !idx_nic || idx_nic.length == 0) {
						$("#pci_roms_id_error").css({'display': 'inline'});
						$("#pci_roms_id_error").html("Invalid or unsupported pci_vendor_code or pci_device_code <br/>");
					}
				}
			}
			/* build up full binary string */
			var fullbinary = bindir + "/" + binary;
			/* Set values in form */
			$("#setdebug").val(unescape(debug));
			$("#gitrevision").val(unescape(revision));
			$("#embed").val(unescape(embed))
			/* Set values for elements in the ADV wizard */
			$("#outputformatadv").val(fullbinary).change();
			/* For all Checkboxes in options div */
			$("#options").find("input:checkbox").each(function(index) {
				var name = $(this).prop("name");
				var value = $(this).prop("checked") ? 1 : 0;
				if (typeof args[name] != "undefined" && value != args[name]) {
					$(this).prop("checked", args[name] == 1);
				}
			});
			/* For all text field in options div */
			$("#options").find("input:text").each(function(index) {
				var name = $(this).prop("name");
				if (typeof args[name] != "undefined") {
					$(this).val(args[name]);
				}
			});
		}

        $("#ipxeimage").submit(function(event) {
                /* stop form from submitting normally */
                event.preventDefault();
                var url = buildcfg();
                if (url) {                
                         window.location.href = url;
                };
        });


        /* About Popup */
        $(function() {

                /* Bind a click event */
                $('#about').on('click', function(e) {

                        /* Prevents the default action to be triggered */
                        e.preventDefault();

                        /* Triggering bPopup when click event is fired */
                        $('#about_pop_up').bPopup({
                                contentContainer:'#about_pop_up',
                                loadUrl: 'about.html'
                        });
                });
                $('#about2').on('click', function(e) {

                        /* Prevents the default action to be triggered */
                        e.preventDefault();

                        /* Triggering bPopup when click event is fired */
                        $('#about_pop_up').bPopup({
                                contentContainer:'#about_pop_up',
                                loadUrl: 'about.html'
                        });
                });

                $('#save').on('click', function(e) {

                        /* Prevents the default action to be triggered */
                        e.preventDefault();
			var self = $(this) //button
			, content = $('.content');

                        /* Triggering bPopup when click event is fired */
                        $('#about_pop_up').bPopup({
                                onOpen: function() {
					var baseURI = document.baseURI.replace(window.location.search,'').replace(/index\.html$/,'');
					var config = buildcfg();
					var data = "<h2>Direct buildcfg URL</h2><p>Use this URL to directly retreive your binary for later use:</p>";
					data += "<br/>" + baseURI + config;
					data += "<br/><h2>Editable Configuration URL</h2><p>Use this URL to adjust your binary's setup:</p>";
					var editcfg = config.replace(/^[^?]*\?/,'?');
					data += "<br/>" + baseURI + editcfg;
			                content.html(data);
				},
				onClose: function() {
					content.empty();
				}
                        });
                });
				setTimeout(loadcfg, 50);
        });

        /* Input file */
        $(function() {
                function handleFileSelect(evt) {
                        var file = evt.target.files[0]; // FileList object

                        // Only process text or unknow file type.
                        if (!file.type.match('text*') && file.type != "") {
                                document.getElementById('list').innerHTML = '<ul style="background-color: red;"> Only text file are supported </ul>';
                                return;
                        }

                        // file is a File objects. List some properties.
                        var output = [];
                        output.push('<li><strong>', escape(file.name), '</strong> (', file.type || 'n/a', ') - ',
                                file.size, ' bytes, last modified: ',
                                file.lastModifiedDate ? file.lastModifiedDate.toLocaleDateString() : 'n/a',
                                '</li>');

                        var reader = new FileReader();
                        // Closure to capture the file information.
                        reader.onload = (function(theFile) {
                                return function(e) {
                                                var content = e.target.result;
                                                $("#embed").val(content);
                                                if (content.indexOf("#!ipxe") === -1) {
                                                        document.getElementById('list').innerHTML =
                                                                '<ul style="background-color: red;"> Not a iPXE script </ul>';
                                                }
                                        };
                        })(file);

                        // Read in the text file as Text.
                        reader.readAsText(file);

                        document.getElementById('list').innerHTML = '<ul>' + output.join('') + '</ul>';
                }
                document.getElementById('embedfile').addEventListener('change', handleFileSelect, false);
        });

        /* Drop file zone */
        $(function() {
                function handleFileSelect(evt) {
                                evt.stopPropagation();
                                evt.preventDefault();

                                //Retrieve the first (and only!) File from the FileList object
                                var file = evt.dataTransfer.files[0]; // FileList object.

                                // Only process textfiles.
                                if (!file.type.match('text*') && file.type != "") {
                                        document.getElementById('list').innerHTML = '<ul style="background-color: red;"> Only text file are supported </ul>';
                                        return;
                                }

                                // file is a File objects. List some properties.
                                var output = [];
                                output.push('<li><strong>', escape(file.name), '</strong> (', file.type || 'n/a', ') - ',
                                        file.size, ' bytes, last modified: ',
                                        file.lastModifiedDate ? file.lastModifiedDate.toLocaleDateString() : 'n/a',
                                        '</li>');

                                var reader = new FileReader();
                                // Closure to capture the file information.
                                reader.onload = (function(theFile) {
                                        return function(e) {
                                                        var content = e.target.result;
                                                        $("#embed").val(content);
                                                        if (content.indexOf("#!ipxe") === -1) {
                                                                document.getElementById('list').innerHTML =
                                                                        '<ul style="background-color: red;"> Not a iPXE script </ul>';
                                                        }
                                                };
                                })(file);

                                // Read in the text file as Text.
                                reader.readAsText(file);

                                document.getElementById('list').innerHTML = '<ul>' + output.join('') + '</ul>';
                                $("#embedfile").val("");
                        }

                        function handleDragOver(evt) {
                                evt.stopPropagation();
                                evt.preventDefault();
                                evt.dataTransfer.dropEffect = 'copy'; // Explicitly show this is a copy.
                        }

                // Setup the dnd listeners.
                var dropZone = document.getElementById('drop_zone');
                dropZone.addEventListener('dragover', handleDragOver, false);
                dropZone.addEventListener('drop', handleFileSelect, false);
        });

        // Check for the various File API support.
        if (!window.File && !window.FileReader) {
                alert('The File APIs are not fully supported by your browser.');
        }

}); /* End DOM ready */
