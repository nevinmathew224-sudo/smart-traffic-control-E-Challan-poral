<?php
/**
 * Smart Traffic System - Challan Intelligence & Vehicle Helper
 * Provides vehicle specifications, statutory legal details, and photographic evidence.
 */

if (!function_exists('getVehicleDetails')) {
    function getVehicleDetails(string $vehicleNo, string $violation = ''): array
    {
        $cleanNo = strtoupper(trim(preg_replace('/[^A-Z0-9]/', '', $vehicleNo)));
        $stateCode = substr($cleanNo, 0, 2);
        $rtoCode = substr($cleanNo, 0, 4);

        // State & RTO resolution
        $rtoNames = [
            'KL01' => 'Thiruvananthapuram Central RTO, Kerala',
            'KL02' => 'Kollam RTO, Kerala',
            'KL03' => 'Pathanamthitta RTO, Kerala',
            'KL04' => 'Alappuzha RTO, Kerala',
            'KL05' => 'Kottayam RTO, Kerala',
            'KL07' => 'Ernakulam (Kochi) RTO, Kerala',
            'KL08' => 'Thrissur RTO, Kerala',
            'KL09' => 'Palakkad RTO, Kerala',
            'KL10' => 'Malappuram RTO, Kerala',
            'KL11' => 'Kozhikode RTO, Kerala',
            'KL12' => 'Wayanad RTO, Kerala',
            'KL13' => 'Kannur RTO, Kerala',
            'KL14' => 'Kasaragod RTO, Kerala',
            'DL01' => 'Mall Road (North Delhi) RTO, Delhi',
            'DL02' => 'IP Estate (Central Delhi) RTO, Delhi',
            'DL03' => 'Sheikh Sarai (South Delhi) RTO, Delhi',
            'DL04' => 'Janakpuri (West Delhi) RTO, Delhi',
            'DL05' => 'Loni Road (North East Delhi) RTO, Delhi',
            'DL06' => 'Sarai Kale Khan RTO, Delhi',
            'DL07' => 'Mayur Vihar (East Delhi) RTO, Delhi',
            'DL08' => 'Wazirpur RTO, Delhi',
            'DL09' => 'Palam (South West Delhi) RTO, Delhi',
            'DL10' => 'Raja Garden RTO, Delhi',
            'HR26' => 'Gurugram North RTO, Haryana',
            'HR51' => 'Faridabad RTO, Haryana',
            'UP16' => 'Gautam Buddha Nagar (Noida) RTO, Uttar Pradesh',
            'UP14' => 'Ghaziabad RTO, Uttar Pradesh',
            'MH12' => 'Pune Regional Transport Office, Maharashtra',
            'MH02' => 'Mumbai West (Andheri) RTO, Maharashtra',
            'KA03' => 'Indiranagar (Bangalore East) RTO, Karnataka',
            'KA01' => 'Koramangala (Bangalore Central) RTO, Karnataka',
            'GJ01' => 'Ahmedabad RTO, Gujarat',
            'PB10' => 'Ludhiana Regional Transport Office, Punjab',
            'RJ14' => 'Jaipur South RTO, Rajasthan',
        ];

        $stateNames = [
            'KL' => 'Kerala Motor Vehicles Department',
            'DL' => 'Transport Department, Government of NCT of Delhi',
            'HR' => 'Haryana Transport Department',
            'UP' => 'Uttar Pradesh Transport Department',
            'MH' => 'Maharashtra Motor Vehicles Department',
            'KA' => 'Karnataka Transport Department',
            'GJ' => 'Gujarat Transport Department',
            'PB' => 'Punjab Transport Department',
            'RJ' => 'Rajasthan Transport Department',
        ];

        $issuingAuthority = $stateNames[$stateCode] ?? 'State Motor Vehicles Department & Traffic Police';
        $rtoAuthority = $rtoNames[$rtoCode] ?? ($rtoNames[$stateCode . '01'] ?? ($stateCode . ' Regional Transport Authority'));

        // Deterministic hash based on vehicle registration
        $hash = crc32($cleanNo);
        $seed = abs($hash);

        $isTwoWheeler = (strcasecmp($violation, 'Helmet') === 0);

        if ($isTwoWheeler) {
            $bikeModels = [
                ['make' => 'Bajaj', 'model' => 'Pulsar 150 Dts-i', 'class' => '2W - MCWG (Motorcycle with Gear)', 'fuel' => 'Petrol', 'body' => 'Two Wheeler / Motorcycle'],
                ['make' => 'Honda', 'model' => 'Activa 6G Deluxe', 'class' => '2W - MCWOG (Scooter without Gear)', 'fuel' => 'Petrol', 'body' => 'Two Wheeler / Scooter'],
                ['make' => 'Hero', 'model' => 'Splendor Plus XTEC', 'class' => '2W - MCWG (Motorcycle with Gear)', 'fuel' => 'Petrol', 'body' => 'Two Wheeler / Motorcycle'],
                ['make' => 'Royal Enfield', 'model' => 'Hunter 350 Dapper', 'class' => '2W - MCWG (Motorcycle with Gear)', 'fuel' => 'Petrol', 'body' => 'Two Wheeler / Cruiser Motorcycle'],
                ['make' => 'TVS', 'model' => 'Jupiter 125 SmartXonnect', 'class' => '2W - MCWOG (Scooter without Gear)', 'fuel' => 'Petrol', 'body' => 'Two Wheeler / Scooter'],
            ];
            $choice = $bikeModels[$seed % count($bikeModels)];
        } else {
            $carModels = [
                ['make' => 'Hyundai', 'model' => 'Creta SX 1.5L CRDi', 'class' => 'LMV - Light Motor Vehicle (Motor Car)', 'fuel' => 'Diesel', 'body' => 'SUV / Compact SUV'],
                ['make' => 'Maruti Suzuki', 'model' => 'Swift ZXi DualJet', 'class' => 'LMV - Light Motor Vehicle (Motor Car)', 'fuel' => 'Petrol', 'body' => 'Hatchback'],
                ['make' => 'Tata Motors', 'model' => 'Nexon EV Empowered+', 'class' => 'LMV - Light Motor Vehicle (Electric)', 'fuel' => 'Electric', 'body' => 'Compact SUV (Electric)'],
                ['make' => 'Mahindra', 'model' => 'XUV700 AX7 Luxury Pack', 'class' => 'LMV - Light Motor Vehicle (Motor Car)', 'fuel' => 'Diesel', 'body' => 'Mid-Size SUV'],
                ['make' => 'Kia', 'model' => 'Seltos HTX 1.5 Smartstream', 'class' => 'LMV - Light Motor Vehicle (Motor Car)', 'fuel' => 'Petrol', 'body' => 'Compact SUV'],
                ['make' => 'Toyota', 'model' => 'Innova Crysta 2.4 VX', 'class' => 'LMV - Light Motor Vehicle (Multi-Utility)', 'fuel' => 'Diesel', 'body' => 'MPV / Multi Utility Vehicle'],
                ['make' => 'Honda', 'model' => 'City 5th Gen ZX CVT', 'class' => 'LMV - Light Motor Vehicle (Motor Car)', 'fuel' => 'Petrol', 'body' => 'Sedan'],
            ];
            $choice = $carModels[$seed % count($carModels)];
        }

        $colors = ['Polar White', 'Phantom Black', 'Metallic Silky Silver', 'Titanium Grey', 'Fiery Crimson Red', 'Pearl Arctic White'];
        $color = $colors[$seed % count($colors)];

        $chassisSuffix = strtoupper(substr(md5($cleanNo . 'chassis'), 0, 8));
        $engineSuffix = strtoupper(substr(md5($cleanNo . 'engine'), 0, 7));

        $regYear = 2019 + ($seed % 6);
        $regMonth = str_pad((string) (($seed % 12) + 1), 2, '0', STR_PAD_LEFT);
        $regDay = str_pad((string) (($seed % 28) + 1), 2, '0', STR_PAD_LEFT);
        $regDate = "$regDay-$regMonth-$regYear";

        $insExpiryYear = $regYear + 6;
        $insDate = "28-11-$insExpiryYear";

        // Formatted registration number with spaces
        $formattedNo = $cleanNo;
        if (preg_match('/^([A-Z]{2})([0-9]{1,2})([A-Z]{1,3})([0-9]{4})$/', $cleanNo, $matches)) {
            $formattedNo = $matches[1] . ' ' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . ' ' . $matches[3] . ' ' . $matches[4];
        }

        return [
            'raw_number' => $cleanNo,
            'formatted_number' => $formattedNo,
            'state_code' => $stateCode,
            'rto_authority' => $rtoAuthority,
            'issuing_authority' => $issuingAuthority,
            'make' => $choice['make'],
            'model' => $choice['model'],
            'vehicle_class' => $choice['class'],
            'vehicle_body' => $choice['body'],
            'fuel_type' => $choice['fuel'],
            'color' => $color,
            'registration_date' => $regDate,
            'chassis_no' => 'MAL' . substr($cleanNo, 0, 2) . 'X' . $chassisSuffix,
            'engine_no' => substr($choice['make'], 0, 2) . ($isTwoWheeler ? '2W' : '4C') . 'E' . $engineSuffix,
            'insurance_policy' => 'ICICI-LOMBARD/POL-' . substr((string) $seed, 0, 6) . ' (Valid up to ' . $insDate . ')',
            'insurance_status' => 'Active & Insured',
            'pucc_number' => 'PUCC/' . $stateCode . '/' . substr((string) $seed, 0, 7),
            'pucc_status' => 'Valid & Certified (BS-VI Compliant)',
            'is_two_wheeler' => $isTwoWheeler,
        ];
    }
}

if (!function_exists('getViolationLegalDetails')) {
    function getViolationLegalDetails(string $violation, float $fineAmount, string $vehicleNo = ''): array
    {
        $vLower = strtolower(trim($violation));
        $fine = $fineAmount > 0 ? $fineAmount : 1000.0;

        // Structured legal details
        if (str_contains($vLower, 'helmet')) {
            $title = 'Riding Without Protective Headgear (Helmet)';
            $statutorySection = 'Section 129 read with Section 194D of the Motor Vehicles Act, 1988 (Amended 2019)';
            $cmvrRule = 'Rule 138(4)(f) of the Central Motor Vehicles Rules (CMVR), 1989';
            $description = 'Operating or riding pillion on a two-wheeled motorcycle in a public place without wearing protective headgear conforming to the Bureau of Indian Standards (BIS/ISI standard IS:4151:2015) securely fastened by chin strap.';
            $prohibitedAct = 'Operating or allowing any person to ride pillion on a two-wheeler on public streets, bridges, or highways without securely wearing an ISI/BIS-certified helmet conforming to prescribed national standards.';
            $radarTelemetry = 'Optical Headgear Detection Node: AI Classifier Flagged [NO_HELMET_ON_RIDER] | Confidence: 99.4%';
            $baseFine = 1000.0;
        } elseif (str_contains($vLower, 'speed')) {
            $title = 'Exceeding Prescribed Speed Limit (Overspeeding)';
            $statutorySection = 'Section 112 read with Section 183(1) of the Motor Vehicles Act, 1988 (Amended 2019)';
            $cmvrRule = 'Rule 118 of the Central Motor Vehicles Rules (CMVR), 1989';
            $description = 'Driving a motor vehicle in excess of the statutory speed limit prescribed by the competent authority or posted speed corridor signboards on public highways and metropolitan transit routes.';
            $prohibitedAct = 'Driving or operating any motor vehicle at a velocity exceeding the maximum statutory ceiling determined for the specific class of vehicle and highway corridor under Section 112 of the Motor Vehicles Act.';
            $radarTelemetry = 'Doppler Radar Sensor Reading: 78 km/h | Posted Corridor Ceiling: 60 km/h | Speed Excess: +18 km/h';
            $baseFine = 2000.0;
        } elseif (str_contains($vLower, 'signal') || str_contains($vLower, 'red')) {
            $title = 'Jumping Red Light / Disobedience of Traffic Control Signals';
            $statutorySection = 'Section 119 read with Section 184 (Dangerous Driving) & Section 177 of the Motor Vehicles Act, 1988';
            $cmvrRule = 'Rule 21 & Tenth Schedule to the Motor Vehicles Act, 1988';
            $description = 'Failing to conform to traffic control signals by proceeding past the designated junction stop line and entering the intersection during the illuminated steady red signal phase.';
            $prohibitedAct = 'Entering an active junction, crossing road stop lines, or navigating through pedestrian crosswalks when the traffic control signal indicates an illuminated red signal light.';
            $radarTelemetry = 'Junction Loop Sensor: Breached Stop-Line 4.80s after Steady Red Light Illumination | Stop Line Intrusion: 6.4m';
            $baseFine = 1500.0;
        } elseif (str_contains($vLower, 'mobile') || str_contains($vLower, 'phone')) {
            $title = 'Using Handheld Mobile Phone / Communication Device While Driving';
            $statutorySection = 'Section 184(c) read with Section 177A of the Motor Vehicles Act, 1988 (Amended 2019)';
            $cmvrRule = 'Rule 21(25) of the Central Motor Vehicles Rules (CMVR), 1989';
            $description = 'Operating, holding, or communicating through a handheld mobile telephone or electronic communication device while driving a motor vehicle in motion in a public corridor.';
            $prohibitedAct = 'Holding, conversing upon, texting, or accessing handheld digital and wireless communication apparatus while physically in control of a motor vehicle in motion on a roadway.';
            $radarTelemetry = 'Telephoto In-Cabin AI Detection: Handheld Device in Driver Right Hand while Vehicle in Motion | Optical Score: 98.1%';
            $baseFine = 1000.0;
        } elseif (str_contains($vLower, 'triple')) {
            $title = 'Triple Riding on Two-Wheeler (Overcrowded Vehicle)';
            $statutorySection = 'Section 128 read with Section 194C of the Motor Vehicles Act, 1988 (Amended 2019)';
            $cmvrRule = 'Rule 138(4)(f) of the Central Motor Vehicles Rules (CMVR), 1989';
            $description = 'Riding a two-wheeled motorcycle or scooter with more than one pillion rider in contravention of statutory capacity ceiling and passenger carriage limits.';
            $prohibitedAct = 'Carrying more than one pillion rider (more than two persons in total) on a two-wheeled motorcycle on public roads, corridors, or highways.';
            $radarTelemetry = 'Multi-Occupancy Optical AI Node: 3 Occupants Detected on Two-Wheeler | AI Detection Score: 99.1%';
            $baseFine = 1000.0;
        } elseif (str_contains($vLower, 'seatbelt') || str_contains($vLower, 'belt')) {
            $title = 'Driving Without Fastened Safety Seatbelt';
            $statutorySection = 'Section 194B(1) of the Motor Vehicles Act, 1988 (Amended 2019)';
            $cmvrRule = 'Rule 138(3) of the Central Motor Vehicles Rules (CMVR), 1989';
            $description = 'Driving a motor vehicle without fastening the prescribed safety seatbelt in contravention of statutory vehicular safety standards.';
            $prohibitedAct = 'Operating or occupying a motor vehicle in motion on a public corridor without securely fastening an approved three-point safety seatbelt.';
            $radarTelemetry = 'In-Cabin Telephoto AI Detection: Unfastened Safety Harness on Driver Seat | Optical Score: 98.7%';
            $baseFine = 1000.0;
        } elseif (str_contains($vLower, 'dangerous') || str_contains($vLower, 'reckless')) {
            $title = 'Dangerous / Reckless Driving Endangering Public Safety';
            $statutorySection = 'Section 184 of the Motor Vehicles Act, 1988 (Amended 2019)';
            $cmvrRule = 'Rule 21 of the Central Motor Vehicles Rules (CMVR), 1989';
            $description = 'Driving in a dangerous manner, reckless multi-lane swerving, cutting across road dividers, or endangering public safety on transit corridors.';
            $prohibitedAct = 'Operating any motor vehicle at a velocity or in a manner that endangers the life or personal safety of other road users and pedestrians.';
            $radarTelemetry = 'Doppler Radar & Trajectory Tracker: Sharp Multi-Lane Swerve Across Divider | Trajectory Deviation: 34°';
            $baseFine = 2500.0;
        } elseif (str_contains($vLower, 'park')) {
            $title = 'Unauthorized Parking / Parking in No-Parking Tow-Away Zone';
            $statutorySection = 'Section 122 read with Section 177 of the Motor Vehicles Act, 1988 (Amended 2019)';
            $cmvrRule = 'Rule 15 of Rules of the Road Regulations, 1989 & Municipal Traffic By-Laws';
            $description = 'Leaving or parking a motor vehicle in a designated No-Parking tow-away zone, blocking pedestrian walkways, obstructing carriage flow, or causing transit danger.';
            $prohibitedAct = 'Leaving any motor vehicle in a public place in such a position or circumstance as to cause or be likely to cause danger, obstruction or undue inconvenience to other users.';
            $radarTelemetry = 'Fixed Urban Surveillance Node #09: Stationary Vehicle in Marked Tow-Away Corridor > 15 Mins | Spatial Obstruction Verified';
            $baseFine = 1000.0;
        } elseif (str_contains($vLower, 'wrong') || str_contains($vLower, 'one way')) {
            $title = 'Driving on Wrong Side / One-Way Restriction Violation';
            $statutorySection = 'Section 184 read with Section 119 of the Motor Vehicles Act, 1988';
            $cmvrRule = 'Tenth Schedule to the Motor Vehicles Act, 1988 (Mandatory Directional Signs)';
            $description = 'Driving against designated flow of traffic, violating one-way corridor restrictions, or navigating on the opposite lane of a divided roadway.';
            $prohibitedAct = 'Driving a motor vehicle in contravention of mandatory one-way traffic signage or proceeding in a direction opposite to designated road flow.';
            $radarTelemetry = 'Directional Loop Array: 180° Velocity Vector Inversion Against Traffic Flow | One-Way Breach Flagged';
            $baseFine = 1500.0;
        } else {
            $title = ucwords($violation);
            $statutorySection = 'Section 177 of the Motor Vehicles Act, 1988 (General Provision for Punishment of Offences)';
            $cmvrRule = 'Central Motor Vehicles Rules (CMVR), 1989';
            $description = 'Contravention of provisions of the Motor Vehicles Act or rules and notifications made thereunder.';
            $prohibitedAct = 'Committing infractions contrary to established traffic safety regulations and vehicular control standards.';
            $radarTelemetry = 'Automated AI Sensor Node: Traffic Rule Violation Flagged and Verified by Monitoring Officer';
            $baseFine = $fine;
        }

        // Statutory fee computation
        $cess = 100.00;
        $portalProcessing = 50.00;
        $compoundFine = max(0, $baseFine - ($cess + $portalProcessing));
        if ($compoundFine <= 0) {
            $compoundFine = $baseFine;
            $cess = 0;
            $portalProcessing = 0;
        }

        $legalConsequences = [
            'Court Referral Notice' => 'Under Section 208 of the Motor Vehicles Act, 1988, failure to compound or discharge this penalty within the statutory timeline of 60 days will cause the offense dossier to be transmitted automatically to the Competent Virtual Traffic Court / Chief Judicial Magistrate (CJM) for summary judicial trial and issuance of legal summons.',
            'Vehicle Blacklisting & VAHAN Lock' => 'Non-compliance triggers automated blacklisting of the vehicle registration number on the National VAHAN 4.0 database. This prohibits issuance or renewal of Fitness Certificates, grant of National/State Permits, transfer of ownership, and issuance of Pollution Under Control Certificates (PUCC).',
            'Driving License Endorsement & Demerit' => 'Penalty points and judicial endorsements are logged against the driving license dossier of the offender under Section 19 of the Motor Vehicles Act. Three or more consecutive serious violations will result in mandatory license suspension for a period not less than three months.',
            'Compounding Timeline' => 'This statutory compounding notice remains valid for 60 calendar days from the date of detection. Post 60 days, compounding privileges expire and standard virtual court recovery procedures apply.'
        ];

        return [
            'title' => $title,
            'statutory_section' => $statutorySection,
            'cmvr_rule' => $cmvrRule,
            'description' => $description,
            'prohibited_act' => $prohibitedAct,
            'radar_telemetry' => $radarTelemetry,
            'base_fine' => $compoundFine,
            'compound_fine' => $compoundFine,
            'original_fine' => $baseFine,
            'cess' => $cess,
            'road_safety_cess' => $cess,
            'portal_processing' => $portalProcessing,
            'processing_fee' => $portalProcessing,
            'total_fine' => $fine,
            'legal_consequences' => $legalConsequences,
        ];
    }
}

if (!function_exists('getChallanEvidence')) {
    function getChallanEvidence(string $vehicleNo, string $violation, int $challanId, ?string $createdAt = null): array
    {
        $vehicle = getVehicleDetails($vehicleNo, $violation);
        $stateCode = $vehicle['state_code'];

        // Realistic locations matching state and Kerala theme
        $locationCorridors = [
            'KL' => [
                'MG Road - Kaloor Junction (Junction Node #04), Ernakulam, Kerala',
                'Palayam - MG Road Corridor (ANPR Node #02), Thiruvananthapuram, Kerala',
                'Mavoor Road Junction (Corridor Node #07), Kozhikode, Kerala',
                'Swaraj Round - Round South (Speed Radar #03), Thrissur, Kerala',
                'NH-66 Edapally Bypass Flyover, Kochi, Kerala',
            ],
            'DL' => [
                'Ring Road - Dhaula Kuan Intersection (Camera Node #08), New Delhi',
                'Outer Ring Road - IIT Flyover Approach, South Delhi',
                'Barakhamba Road - Connaught Place Outer Circle, New Delhi',
            ],
            'HR' => [
                'MG Road - IFFCO Chowk Intersection (Node #03), Gurugram, Haryana',
                'Golf Course Road - Cyber City Underpass, Gurugram, Haryana',
            ],
            'UP' => [
                'Noida-Greater Noida Expressway (KM 14 Sensor Node), Noida, UP',
                'DND Flyway Toll Plaza - Sector 18 Approach, Gautam Buddha Nagar, UP',
            ],
            'MH' => [
                'Senapati Bapat Road - University Junction, Pune, Maharashtra',
                'Western Express Highway - Andheri Flyover Node #05, Mumbai, Maharashtra',
            ],
            'KA' => [
                'MG Road & Brigade Road Junction (ANPR Node #04), Bangalore, Karnataka',
                'Outer Ring Road - Marathahalli Junction, Bangalore, Karnataka',
            ],
        ];

        $corridorList = $locationCorridors[$stateCode] ?? $locationCorridors['KL'];
        $cleanNo = strtoupper(trim(preg_replace('/[^A-Z0-9]/', '', $vehicleNo)));
        $seed = abs((int) crc32($cleanNo) + $challanId);
        $location = $corridorList[$seed % count($corridorList)];

        $vLower = strtolower(trim($violation));

        if (str_contains($vLower, 'helmet')) {
            $frontImage = 'assets/evidence/helmet_front_cam.jpg';
            $sideImage = 'assets/evidence/helmet_side_cam.jpg';
            $frontCameraId = 'KL-ITES-CAM-54 (High-Speed ANPR Headgear Detection Node)';
            $sideCameraId = 'KL-ITES-ENF-781 (Lateral Urban Corridor Profiler)';
            $frontLabel = 'FRONT VIEW • RIDER WITHOUT HELMET DETECTED (NO PROTECTIVE HEADGEAR)';
            $sideLabel = 'SIDE PROFILE VIEW • RIDER WITHOUT HELMET IN MOTION (CLEAR BARE HEAD)';
            $hud1 = 'HEADGEAR OCR: NOT DETECTED (BARE HEAD)';
            $hud2 = 'CONFIDENCE: 99.4% VIOLATION CONFIRMED';
            $radarNode = 'Optical AI Headgear Classifier IS-4151 Node';
            $anprScore = '99.4% Headgear OCR Confidence';
            $lane = 'Lane 2 (Urban Transit Corridor)';
        } elseif (str_contains($vLower, 'speed')) {
            $frontImage = 'assets/evidence/car_front_cam.jpg';
            $sideImage = 'assets/evidence/car_side_cam.jpg';
            $frontCameraId = 'KL-ITES-ANPR-01 (UltraHD Automated Highway Plate Capture)';
            $sideCameraId = 'KL-ITES-RADAR-12 (Multi-Lane Speed Measurement Scanner)';
            $frontLabel = 'HIGHWAY ANPR FRONT VIEW • VEHICLE SPEED EXCEEDED';
            $sideLabel = 'LATERAL DOPPLER RADAR VIEW • 78 KM/H IN 60 KM/H LIMIT';
            $hud1 = 'RECORDED SPEED: 78 KM/H (LIMIT 60 KM/H)';
            $hud2 = 'SPEED VIOLATION: +18 KM/H DETECTED';
            $radarNode = 'Dual 77 GHz Frequency-Modulated Continuous-Wave (FMCW) Radar';
            $anprScore = '98.8% OCR Confidence Match';
            $lane = 'Lane 1 (Express Fast Corridor)';
        } elseif (str_contains($vLower, 'signal') || str_contains($vLower, 'red')) {
            $frontImage = 'assets/evidence/signal_front_cam.jpg';
            $sideImage = 'assets/evidence/signal_side_cam.jpg';
            $frontCameraId = 'KL-ITES-CAM-04 (Red Light Enforcement & Stop-Line ANPR Node)';
            $sideCameraId = 'KL-ITES-RLV-14A (Intersection Lateral Phase Tracker)';
            $frontLabel = 'JUNCTION FRONT VIEW • STEADY RED SIGNAL ILLUMINATED & BREACHED';
            $sideLabel = 'INTERSECTION LATERAL VIEW • VEHICLE CROSSING ON RED SIGNAL (04.18s)';
            $hud1 = 'SIGNAL PHASE: STEADY RED LIGHT (BREACH)';
            $hud2 = 'RED ELAPSED: 04.18s • STOP LINE INTRUSION';
            $radarNode = 'Optical Red Light Loop & 3D Intersection Radar Array';
            $anprScore = '98.8% Stop-Line Optical Confidence';
            $lane = 'Intersection Center (Stop-Line Crossed)';
        } elseif (str_contains($vLower, 'mobile') || str_contains($vLower, 'phone')) {
            $frontImage = 'assets/evidence/mobile_front_cam.jpg';
            $sideImage = 'assets/evidence/mobile_side_cam.jpg';
            $frontCameraId = 'KL-ITES-CAM-04 (Windshield Telephoto In-Cabin Inspection Node)';
            $sideCameraId = 'KL-ITES-SF-145 (Lateral Driver Cabin Window Surveillance)';
            $frontLabel = 'WINDSHIELD TELEPHOTO VIEW • DRIVER HOLDING MOBILE PHONE TO EAR';
            $sideLabel = 'LATERAL WINDOW VIEW • HANDHELD PHONE SCREEN USE WHILE DRIVING';
            $hud1 = 'DRIVER CABIN: HANDHELD PHONE IN USE';
            $hud2 = 'DISTRACTION AI: 98.1% CONFIDENCE CONFIRMED';
            $radarNode = 'High-Resolution In-Cabin Optical AI Sensor';
            $anprScore = '98.1% In-Cabin AI Detection Confidence';
            $lane = 'Lane 1 (Express Corridor)';
        } elseif (str_contains($vLower, 'triple')) {
            $frontImage = 'assets/evidence/triple_front_cam.jpg';
            $sideImage = 'assets/evidence/triple_side_cam.jpg';
            $frontCameraId = 'KL-ITES-CAM-88 (Urban Multi-Occupancy ANPR Headcount Node)';
            $sideCameraId = 'KL-ITES-ENF-412 (Lateral Passenger Count Corridor Scanner)';
            $frontLabel = 'FRONT VIEW • TRIPLE RIDING DETECTED (3 OCCUPANTS ON TWO-WHEELER)';
            $sideLabel = 'SIDE PROFILE VIEW • THREE RIDERS SEATED ON SINGLE MOTORCYCLE';
            $hud1 = 'HEADCOUNT OCR: 3 PERSONS ON TWO-WHEELER';
            $hud2 = 'CAPACITY VIOLATION: SEC 128 / 194C CONFIRMED';
            $radarNode = 'Automated Two-Wheeler Multi-Occupancy AI Classifier';
            $anprScore = '99.1% Multi-Passenger OCR Confidence';
            $lane = 'Lane 2 (Urban Transit Corridor)';
        } elseif (str_contains($vLower, 'seatbelt') || str_contains($vLower, 'belt')) {
            $frontImage = 'assets/evidence/seatbelt_front_cam.jpg';
            $sideImage = 'assets/evidence/seatbelt_side_cam.jpg';
            $frontCameraId = 'KL-ITES-CABIN-09 (High-Resolution In-Cabin Seatbelt Detection Node)';
            $sideCameraId = 'KL-ITES-SF-204 (Lateral Pillar Window Inspection Camera)';
            $frontLabel = 'WINDSHIELD TELEPHOTO VIEW • DRIVER UNBELTED (NO SAFETY HARNESS)';
            $sideLabel = 'LATERAL WINDOW VIEW • UNFASTENED SEATBELT RETRACTED ON PILLAR';
            $hud1 = 'SEATBELT STATUS: UNFASTENED (BARE CHEST)';
            $hud2 = 'IN-CABIN AI: 98.7% CONFIDENCE CONFIRMED';
            $radarNode = 'Dual In-Cabin High-Resolution Optical AI Scanner';
            $anprScore = '98.7% In-Cabin Seatbelt Detection Confidence';
            $lane = 'Lane 1 (Express Fast Corridor)';
        } elseif (str_contains($vLower, 'dangerous') || str_contains($vLower, 'reckless')) {
            $frontImage = 'assets/evidence/dangerous_front_cam.jpg';
            $sideImage = 'assets/evidence/dangerous_side_cam.jpg';
            $frontCameraId = 'KL-ITES-HWY-07 (Highway Multi-Lane Surveillance & Trajectory Array)';
            $sideCameraId = 'KL-ITES-RADAR-412 (Corridor Dynamic Maneuver Scanner)';
            $frontLabel = 'HIGHWAY CORRIDOR VIEW • RECKLESS MULTI-LANE SWERVE ACROSS MARKINGS';
            $sideLabel = 'LATERAL RADAR VIEW • SHARP CUT-OFF MANEUVER ENDANGERING TRANSIT';
            $hud1 = 'MANEUVER: RECKLESS MULTI-LANE DRIFT';
            $hud2 = 'DANGER TELEMETRY: SEVERE CORRIDOR RISK DETECTED';
            $radarNode = '3D Doppler Trajectory Analysis & Multi-Lane Radar';
            $anprScore = '98.9% Optical & Radar Trajectory Match';
            $lane = 'Center Corridor (Multi-Lane Swerve)';
        } elseif (str_contains($vLower, 'park')) {
            $frontImage = 'assets/evidence/parking_front_cam.jpg';
            $sideImage = 'assets/evidence/parking_side_cam.jpg';
            $frontCameraId = 'KL-ITES-CAM-09 (High-Definition Urban Surveillance & No-Parking ANPR Node)';
            $sideCameraId = 'KL-ITES-CAM-09B (Lateral Corridor Profiler & Obstruction Scanner)';
            $frontLabel = 'STREET SURVEILLANCE FRONT VIEW • VEHICLE STATIONARY IN TOW-AWAY ZONE';
            $sideLabel = 'LATERAL PROFILE VIEW • FOOTPATH & CORRIDOR OBSTRUCTION CONFIRMED';
            $hud1 = 'PARKING STATUS: NO-PARKING ZONE BREACH';
            $hud2 = 'DURATION: >15 MINS • FOOTPATH OBSTRUCTION';
            $radarNode = 'Automated ANPR Static Obstruction AI Node';
            $anprScore = '99.2% License Plate OCR Match';
            $lane = 'Curb Lane / Footpath Corridor (Tow-Away Zone)';
        } elseif (str_contains($vLower, 'wrong') || str_contains($vLower, 'one way')) {
            $frontImage = 'assets/evidence/wrongside_front_cam.jpg';
            $sideImage = 'assets/evidence/wrongside_side_cam.jpg';
            $frontCameraId = 'KL-ITES-CAM-724 (Directional Flow & One-Way Vector Enforcement Node)';
            $sideCameraId = 'KL-ITES-JCT-04 (Intersection Geometry & Traffic Restriction Cam)';
            $frontLabel = 'DIRECTIONAL CORRIDOR VIEW • VEHICLE HEADING AGAINST LAWFUL TRAFFIC FLOW';
            $sideLabel = 'INTERSECTION LATERAL VIEW • ENTRY AGAINST MANDATORY ONE-WAY ARROWS';
            $hud1 = 'VECTOR ERROR: 180° REVERSE DIRECTION';
            $hud2 = 'CRITICAL ALERT: ONE-WAY FLOW BREACH DETECTED';
            $radarNode = 'High-Speed Directional Velocity Vector Sensor Array';
            $anprScore = '99.3% Directional Velocity Violation Match';
            $lane = 'Counter-Flow Corridor (Opposite Direction)';
        } else {
            $frontImage = 'assets/evidence/car_front_cam.jpg';
            $sideImage = 'assets/evidence/car_side_cam.jpg';
            $frontCameraId = 'KL-ITES-ANPR-01 (UltraHD Automated Highway Plate Capture)';
            $sideCameraId = 'KL-ITES-RADAR-12 (Multi-Lane Speed Measurement Scanner)';
            $frontLabel = 'HIGHWAY ANPR FRONT VIEW • VEHICLE SPEED EXCEEDED';
            $sideLabel = 'LATERAL DOPPLER RADAR VIEW • 78 KM/H IN 60 KM/H LIMIT';
            $hud1 = 'RECORDED SPEED: 78 KM/H (LIMIT 60 KM/H)';
            $hud2 = 'SPEED VIOLATION: +18 KM/H DETECTED';
            $radarNode = 'Dual 77 GHz Frequency-Modulated Continuous-Wave (FMCW) Radar';
            $anprScore = '98.8% OCR Confidence Match';
            $lane = 'Lane 1 (Express Fast Corridor)';
        }

        // Coordinates for realistic GPS
        $lat = 9.9816 + ((($challanId % 10) - 5) * 0.0052);
        $lng = 76.2798 + ((($challanId % 8) - 4) * 0.0048);
        $gpsFormatted = sprintf("%.4f° N, %.4f° E", $lat, $lng);

        $offenseTime = $createdAt ? date('d M Y, h:i:s A', strtotime($createdAt)) : date('d M Y, h:i:s A');
        $digitalHash = strtoupper(hash('sha256', "CHALLAN-$challanId-$vehicleNo-$offenseTime-ITES-KERALA-MVD"));

        return [
            'front_image' => $frontImage,
            'side_image' => $sideImage,
            'front_label' => $frontLabel,
            'side_label' => $sideLabel,
            'hud_1' => $hud1,
            'hud_2' => $hud2,
            'front_camera_id' => $frontCameraId,
            'side_camera_id' => $sideCameraId,
            'radar_node' => $radarNode,
            'anpr_score' => $anprScore,
            'lane' => $lane,
            'location' => $location,
            'gps_coordinates' => $gpsFormatted,
            'offense_time' => $offenseTime,
            'digital_hash' => $digitalHash,
            'enforcement_system' => 'Automated Intelligent Traffic Enforcement System (ITES Tier-3 AI)',
            'verifying_officer' => 'Motor Vehicles Inspector (MVI) / Authorized Digital Signatory',
        ];
    }
}

