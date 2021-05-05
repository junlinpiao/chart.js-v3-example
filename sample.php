function rui_register_scripts() {
	wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js' );
}

add_action( 'wp_enqueue_scripts', 'rui_register_scripts' );


// Shortcode for product description in single product page's Product Data Tabs
add_shortcode('financial_chart', 'rui_shortcode_graph');
function rui_shortcode_graph($atts){
	$atts = shortcode_atts(
        array(
            'csv_filename' => '',
		), 
		$atts 
	);
	
	if ( $atts['csv_filename'] == '' ) {
		return "<p>Csv file not specified!</p>";
	}

	$filepath = ABSPATH . "csv_data/" . $atts['csv_filename'];
	if ( ! file_exists( $filepath ) ) {
		return "<p>'".$atts['csv_filename']."' doesn't exist!</p>"; 
	} 

	//Open our CSV file using the fopen function.
	$fh = fopen( $filepath, "r" );

	//Setup a PHP array to hold our CSV rows.
	$csvData = array();

	//Loop through the rows in our CSV file and add them to
	//the PHP array that we created above.
	while (($row = fgetcsv($fh, 0, ",")) !== FALSE) {
		$csvData[] = $row;
	}

	$point_count_year = 8;
	$row_len = count( $csvData );
	
	$summary_data = array();
	$prev_year_str = "";
	$year_items = array();
	$year_count = 0;
	for ( $i=1; $i<$row_len; $i++ ) {
		$cur_year_str = trim( $csvData[$i][0] );
		$cur_year_str = explode("-",$cur_year_str)[0];
		if ( $prev_year_str == "" ) {
			$prev_year_str = $cur_year_str;
		}
		$year_count++;
		$csvData[$i][1] = trim( str_replace( "%", "", $csvData[$i][1] ) );
		$year_items[] = floatval( $csvData[$i][1] );
		if ( $cur_year_str != $prev_year_str || $i == $row_len-1 ) {
			$period = intval( $year_count / $point_count_year );
			if ( $period == 0 ) {
				$period = 1;
			}
			for ( $k=0; $k<$point_count_year; $k++ ) {
				$year_pt_idx = $k * $period;
				if ( $year_pt_idx >= $year_count ) {
					$year_pt_idx = $year_count - 1;
				}
				$summary_data[] = [ $prev_year_str, $year_items[$year_pt_idx] ];
			}
			$year_items = array();
			$year_count = 0;
		}
		$prev_year_str = $cur_year_str;
	}

	ob_start(); ?>
	
	<div class="finance-chart">
		<canvas id="myChart" width=800 height=400></canvas>
	</div>

	<script>
		var ctx = document.getElementById('myChart');
		var ctx_line_width = 3;
		var ctx_gap_padding = 10;
		var ctx_axis_title_font = 
		{
			size: 20,
			weight: 'bold',
			lineHeight: 1.2,
		};

		function handle_responsive(x) {
			if (x.matches) { // If media query matches
				ctx.height = 640;
				ctx_line_width = 1.5;
				ctx_gap_padding = 5;
				ctx_axis_title_font = {
					size: 16,
					weight: 'bold',
					lineHeight: 1.2,
				};
			} else {
				ctx.height = 400;
				ctx_line_width = 3;
				ctx_gap_padding = 10;
				ctx_axis_title_font = {
					size: 20,
					weight: 'bold',
					lineHeight: 1.2,
				};
			}
		}

		var responsive_matcher = window.matchMedia("(max-width: 480px)");
		handle_responsive(responsive_matcher);
		responsive_matcher.addListener(handle_responsive);

		
		var time_points = [
			<?php 
				foreach ( $summary_data as $row ) {
					echo "'" . $row[0] . "'" . ', ';
				}
			?>
		];
		var price_datasets = [{
			data: [
				<?php
					foreach( $summary_data as $row ) {
						echo $row[1] . ', ';
					} 
				?>
			],
			borderColor: 'white',
			fill: false,
			tension: 0.4,
		},];

		var myChart = new Chart(ctx, {
			type: 'line',
			data: {
				labels: time_points,
				datasets: price_datasets,
			},
			options: {
				responsive: true,
				plugins: {
					legend: {
						display: false,
					},
					// title: {
					// 	display: true,
					// 	text: "THIS IS TITLE",
					// 	font: {
					// 		size: 28
					// 	}
					// },
				},
				scales: {
					x: {
						grid: {
							display: false,
							drawBorder: true,
                            color: 'white',
						},
						ticks: {
							display: true,
							callback: (label, index, labels) => {
								if ( index % 8 == 4 ) {
									return time_points[index];
								} else {
									// return "";
								}
							}
						},
						title: {
							display: true,
							text: 'Year',
							color: 'white',
							font: ctx_axis_title_font,
							padding: {top: ctx_gap_padding, left: 0, right: 0, bottom: 0}
						}
					},
					y: {
						grid: {
							display: false,
							drawBorder: true,
						},
						ticks: {
							display: true,
						},
						title: {
							display: true,
							text: 'Sum %',
							color: 'white',
							font: ctx_axis_title_font,
							padding: {top: 0, left: 0, right: 0, bottom: ctx_gap_padding}
						}
					},
				},
				elements: {
					line: {
						borderWidth: ctx_line_width,
					},
					point: {
						display: false,
						pointStyle: 'none',
						radius: 0,
					}
				}
			}
		});
	</script>

	<?php
	$content = ob_get_clean();
	return $content;
	
}
