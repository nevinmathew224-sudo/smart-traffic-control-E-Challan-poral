import os
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.patches as patches
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch, Circle, Polygon
import numpy as np

OUTPUT_DIR = r"c:\xampp\htdocs\smart_traffic\report_assets\diagrams"
os.makedirs(OUTPUT_DIR, exist_ok=True)

plt.rcParams['font.sans-serif'] = 'DejaVu Sans'
plt.rcParams['font.family'] = 'sans-serif'

def draw_box(ax, x, y, w, h, text, bg_color='#EBF4FF', border_color='#1E40AF', text_color='#1E293B', fontsize=10, bold=False, subtext=None, corner_radius=0.03):
    box = FancyBboxPatch((x, y), w, h, boxstyle=f"round,pad=0.02,rounding_size={corner_radius}",
                         facecolor=bg_color, edgecolor=border_color, linewidth=1.5, zorder=2)
    ax.add_patch(box)
    weight = 'bold' if bold else 'normal'
    if subtext:
        ax.text(x + w/2, y + h*0.62, text, ha='center', va='center', fontsize=fontsize, fontweight='bold', color=text_color, zorder=3)
        ax.text(x + w/2, y + h*0.32, subtext, ha='center', va='center', fontsize=fontsize-2, color=text_color, zorder=3)
    else:
        ax.text(x + w/2, y + h/2, text, ha='center', va='center', fontsize=fontsize, fontweight=weight, color=text_color, zorder=3)
    return box

def draw_arrow(ax, x1, y1, x2, y2, label=None, color='#334155', rad=0.0, lw=1.5, label_offset=(0, 0.02), fontsize=8):
    style = f"arc3,rad={rad}"
    arrow = FancyArrowPatch((x1, y1), (x2, y2), connectionstyle=style,
                            arrowstyle='-|>', color=color, mutation_scale=15, linewidth=lw, zorder=4)
    ax.add_patch(arrow)
    if label:
        mx = (x1 + x2) / 2 + label_offset[0]
        my = (y1 + y2) / 2 + label_offset[1]
        ax.text(mx, my, label, ha='center', va='center', fontsize=fontsize, color='#1E293B',
                bbox=dict(boxstyle='square,pad=0.15', facecolor='#FFFFFF', edgecolor='none', alpha=0.9), zorder=5)

# 1. SYSTEM ARCHITECTURE
def gen_architecture():
    fig, ax = plt.subplots(figsize=(12, 7.5), dpi=300)
    ax.set_xlim(0, 12)
    ax.set_ylim(0, 8)
    ax.axis('off')
    
    # Outer frame
    draw_box(ax, 0.2, 0.2, 11.6, 7.6, "", bg_color='#F8FAFC', border_color='#CBD5E1', corner_radius=0.05)
    ax.text(6, 7.45, "Figure 4.1: Three-Tier System Architecture of e-Challan Portal", ha='center', va='center', fontsize=12, fontweight='bold', color='#0F172A')

    # Tier 1: Client Layer
    draw_box(ax, 0.6, 5.0, 10.8, 2.0, "", bg_color='#EFF6FF', border_color='#3B82F6', corner_radius=0.04)
    ax.text(0.9, 6.75, "PRESENTATION TIER (CLIENT INTERFACE)", ha='left', va='center', fontsize=10, fontweight='bold', color='#1E40AF')
    
    draw_box(ax, 0.9, 5.3, 2.4, 1.2, "Citizen Portal\nWeb Browser", subtext="HTML5, CSS3, JS", bg_color='#DBEAFE', border_color='#2563EB', bold=True)
    draw_box(ax, 3.6, 5.3, 2.4, 1.2, "Admin / Officer\nCommand Center", subtext="Glassmorphic Dashboard", bg_color='#DBEAFE', border_color='#2563EB', bold=True)
    draw_box(ax, 6.3, 5.3, 2.4, 1.2, "Vehicle Enquiry\nMobile / Tablet", subtext="Responsive Viewport", bg_color='#DBEAFE', border_color='#2563EB', bold=True)
    draw_box(ax, 9.0, 5.3, 2.1, 1.2, "QR / UPI Gateway\nScanning Interface", subtext="Dynamic Payment", bg_color='#DBEAFE', border_color='#2563EB', bold=True)

    # Tier 2: Application / Business Logic Layer
    draw_box(ax, 0.6, 2.6, 10.8, 2.0, "", bg_color='#ECFDF5', border_color='#10B981', corner_radius=0.04)
    ax.text(0.9, 4.35, "APPLICATION TIER (APACHE HTTP SERVER & PHP 8.x RUNTIME ENGINE)", ha='left', va='center', fontsize=10, fontweight='bold', color='#065F46')

    draw_box(ax, 0.9, 2.9, 2.1, 1.2, "Auth & Security\nEngine", subtext="CSRF / Session / Hash", bg_color='#D1FAE5', border_color='#059669', bold=True)
    draw_box(ax, 3.3, 2.9, 2.4, 1.2, "Challan Processing\nController", subtext="save_challan.php", bg_color='#D1FAE5', border_color='#059669', bold=True)
    draw_box(ax, 6.0, 2.9, 2.4, 1.2, "RTO & Vehicle Specs\nIntelligence Engine", subtext="challan_helper.php", bg_color='#D1FAE5', border_color='#059669', bold=True)
    draw_box(ax, 8.7, 2.9, 2.4, 1.2, "Payment & Invoice\nGenerator", subtext="pay.php / receipt.php", bg_color='#D1FAE5', border_color='#059669', bold=True)

    # Tier 3: Database & File Storage Layer
    draw_box(ax, 0.6, 0.5, 10.8, 1.7, "", bg_color='#FFFBEB', border_color='#F59E0B', corner_radius=0.04)
    ax.text(0.9, 1.95, "DATA TIER (RELATIONAL STORAGE & EVIDENCE REPOSITORY)", ha='left', va='center', fontsize=10, fontweight='bold', color='#92400E')

    draw_box(ax, 0.9, 0.7, 3.2, 1.0, "MySQL / MariaDB RDBMS", subtext="traffic_system Database", bg_color='#FEF3C7', border_color='#D97706', bold=True)
    draw_box(ax, 4.4, 0.7, 3.2, 1.0, "Evidence Image Vault", subtext="/assets/evidence/ (Dual-Cam)", bg_color='#FEF3C7', border_color='#D97706', bold=True)
    draw_box(ax, 7.9, 0.7, 3.2, 1.0, "Audit Logs & Export Subsystem", subtext="CSV Exporter & Archival", bg_color='#FEF3C7', border_color='#D97706', bold=True)

    # Inter-tier arrows
    draw_arrow(ax, 2.1, 5.0, 2.1, 4.6, label="HTTP / HTTPS", label_offset=(0, 0))
    draw_arrow(ax, 4.8, 5.0, 4.8, 4.6, label="REST / POST", label_offset=(0, 0))
    draw_arrow(ax, 7.5, 5.0, 7.5, 4.6, label="Enquiry Request", label_offset=(0, 0))
    draw_arrow(ax, 10.0, 5.0, 10.0, 4.6, label="UPI Webhook", label_offset=(0, 0))

    draw_arrow(ax, 2.5, 2.6, 2.5, 2.2, label="SQL Query", label_offset=(0, 0))
    draw_arrow(ax, 6.0, 2.6, 6.0, 2.2, label="File I/O", label_offset=(0, 0))
    draw_arrow(ax, 9.5, 2.6, 9.5, 2.2, label="Transactions", label_offset=(0, 0))

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_1_architecture.png"), bbox_inches='tight')
    plt.close()

# 2. USE CASE DIAGRAM
def gen_use_case():
    fig, ax = plt.subplots(figsize=(11, 8), dpi=300)
    ax.set_xlim(0, 11)
    ax.set_ylim(0, 9)
    ax.axis('off')

    ax.text(5.5, 8.6, "Figure 4.2: Comprehensive Use Case Diagram", ha='center', va='center', fontsize=12, fontweight='bold', color='#0F172A')

    # System boundary box
    system_box = FancyBboxPatch((2.2, 0.4), 6.6, 7.9, boxstyle="square,pad=0.02",
                                facecolor='#F8FAFC', edgecolor='#0F172A', linewidth=2, zorder=1)
    ax.add_patch(system_box)
    ax.text(5.5, 8.0, "Traffic Control & e-Challan System Boundary", ha='center', va='center', fontsize=11, fontweight='bold', color='#1E3A8A')

    def draw_actor(x, y, name):
        # Head
        c = Circle((x, y + 0.35), 0.18, facecolor='#E2E8F0', edgecolor='#0F172A', linewidth=1.5, zorder=4)
        ax.add_patch(c)
        # Body
        ax.plot([x, x], [y + 0.17, y - 0.25], color='#0F172A', linewidth=1.8, zorder=4)
        # Arms
        ax.plot([x - 0.25, x + 0.25], [y + 0.05, y + 0.05], color='#0F172A', linewidth=1.8, zorder=4)
        # Legs
        ax.plot([x, x - 0.22], [y - 0.25, y - 0.6], color='#0F172A', linewidth=1.8, zorder=4)
        ax.plot([x, x + 0.22], [y - 0.25, y - 0.6], color='#0F172A', linewidth=1.8, zorder=4)
        # Name
        ax.text(x, y - 0.8, name, ha='center', va='top', fontsize=9, fontweight='bold', color='#0F172A')

    # Left Actors
    draw_actor(1.0, 6.2, "Citizen /\nVehicle Owner")
    draw_actor(1.0, 2.2, "Unregistered\nVisitor")

    # Right Actors
    draw_actor(10.0, 6.5, "Traffic Officer /\nEnforcement Staff")
    draw_actor(10.0, 3.5, "System Admin")
    draw_actor(10.0, 1.2, "Banking / UPI\nGateway")

    use_cases = [
        (5.5, 7.4, "Search Vehicle / RC Status", 0.08),
        (5.5, 6.6, "View Violations & Evidence Photos", 0.08),
        (5.5, 5.8, "Make Payment via Dynamic QR", 0.08),
        (5.5, 5.0, "Download Official Tax Receipt", 0.08),
        (5.5, 4.2, "User Login & Profile Management", 0.08),
        (5.5, 3.4, "Issue Challan & Attach Evidence", 0.08),
        (5.5, 2.6, "Manage Violations & Legal Rules", 0.08),
        (5.5, 1.8, "Analytics, GIS Map & Reporting", 0.08),
        (5.5, 1.0, "Export Challans & Audit CSV", 0.08),
    ]

    for ux, uy, utext, r in use_cases:
        ellipse = patches.Ellipse((ux, uy), 3.8, 0.6, facecolor='#EFF6FF', edgecolor='#2563EB', linewidth=1.5, zorder=3)
        ax.add_patch(ellipse)
        ax.text(ux, uy, utext, ha='center', va='center', fontsize=8.5, fontweight='bold', color='#1E40AF', zorder=4)

    # Connect Citizen
    for uy in [7.4, 6.6, 5.8, 5.0, 4.2]:
        ax.plot([1.2, 3.6], [6.2, uy], color='#475569', linestyle='--', linewidth=1.2, zorder=2)

    # Connect Visitor
    for uy in [7.4, 4.2]:
        ax.plot([1.2, 3.6], [2.2, uy], color='#475569', linestyle='--', linewidth=1.2, zorder=2)

    # Connect Traffic Officer
    for uy in [3.4, 2.6]:
        ax.plot([9.8, 7.4], [6.5, uy], color='#475569', linestyle='--', linewidth=1.2, zorder=2)

    # Connect Admin
    for uy in [3.4, 2.6, 1.8, 1.0]:
        ax.plot([9.8, 7.4], [3.5, uy], color='#475569', linestyle='--', linewidth=1.2, zorder=2)

    # Connect Payment Gateway
    ax.plot([9.8, 7.4], [1.2, 5.8], color='#475569', linestyle='--', linewidth=1.2, zorder=2)

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_2_use_case.png"), bbox_inches='tight')
    plt.close()

# 3. DFD LEVEL 0 (CONTEXT DIAGRAM)
def gen_dfd_0():
    fig, ax = plt.subplots(figsize=(10, 6.5), dpi=300)
    ax.set_xlim(0, 10)
    ax.set_ylim(0, 6.5)
    ax.axis('off')

    ax.text(5.0, 6.1, "Figure 4.3: Data Flow Diagram (DFD Level 0 - Context Level)", ha='center', va='center', fontsize=11, fontweight='bold', color='#0F172A')

    # Central Process
    proc_circle = Circle((5.0, 3.2), 1.35, facecolor='#FEF3C7', edgecolor='#D97706', linewidth=2, zorder=3)
    ax.add_patch(proc_circle)
    ax.text(5.0, 3.5, "0.0", ha='center', va='center', fontsize=11, fontweight='bold', color='#92400E', zorder=4)
    ax.text(5.0, 3.1, "Traffic Control &\ne-Challan System", ha='center', va='center', fontsize=9.5, fontweight='bold', color='#78350F', zorder=4)

    # External Entities
    # 1. Citizen / Vehicle Owner (Left)
    draw_box(ax, 0.4, 2.5, 2.2, 1.4, "Citizen /\nRoad User", subtext="External Entity", bg_color='#EFF6FF', border_color='#2563EB', bold=True)
    # 2. Traffic Police / Admin (Top)
    draw_box(ax, 3.9, 4.8, 2.2, 1.1, "Traffic Authority /\nAdmin Officer", subtext="External Entity", bg_color='#EFF6FF', border_color='#2563EB', bold=True)
    # 3. Banking / UPI Gateway (Right)
    draw_box(ax, 7.4, 2.5, 2.2, 1.4, "UPI / Payment\nGateway Service", subtext="External Entity", bg_color='#EFF6FF', border_color='#2563EB', bold=True)
    # 4. RTO & ANPR Camera Network (Bottom)
    draw_box(ax, 3.9, 0.6, 2.2, 1.1, "ANPR Cameras &\nRTO Registry", subtext="Telemetry Entity", bg_color='#EFF6FF', border_color='#2563EB', bold=True)

    # Flows to/from Citizen
    draw_arrow(ax, 2.6, 3.5, 3.7, 3.6, label="Vehicle Enquiry / Login", label_offset=(0, 0.15))
    draw_arrow(ax, 3.7, 2.8, 2.6, 2.9, label="Challan Details & Receipts", label_offset=(0, -0.15))

    # Flows to/from Admin
    draw_arrow(ax, 4.6, 4.8, 4.6, 4.5, label="Violation Entry / Login", label_offset=(-0.8, 0))
    draw_arrow(ax, 5.4, 4.5, 5.4, 4.8, label="Reports & Analytics", label_offset=(0.8, 0))

    # Flows to/from Payment Gateway
    draw_arrow(ax, 6.3, 3.5, 7.4, 3.5, label="Payment Initiation", label_offset=(0, 0.15))
    draw_arrow(ax, 7.4, 2.8, 6.3, 2.8, label="Confirmation Webhook", label_offset=(0, -0.15))

    # Flows to/from ANPR / RTO
    draw_arrow(ax, 4.6, 1.7, 4.6, 2.0, label="Vehicle & Violation Telemetry", label_offset=(-1.2, 0))
    draw_arrow(ax, 5.4, 2.0, 5.4, 1.7, label="Lookup RC Specifications", label_offset=(1.1, 0))

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_3_dfd_level_0.png"), bbox_inches='tight')
    plt.close()

# 4. DFD LEVEL 1
def gen_dfd_1():
    fig, ax = plt.subplots(figsize=(12, 8.5), dpi=300)
    ax.set_xlim(0, 12)
    ax.set_ylim(0, 8.5)
    ax.axis('off')

    ax.text(6.0, 8.1, "Figure 4.4: Data Flow Diagram (DFD Level 1 - Detailed Functional Decomposition)", ha='center', va='center', fontsize=12, fontweight='bold', color='#0F172A')

    # Processes (Circles or rounded boxes)
    processes = [
        (1.0, 6.0, "1.0", "Authentication &\nSession Management"),
        (4.8, 6.0, "2.0", "Vehicle Enquiry &\nRTO RC Lookup"),
        (8.6, 6.0, "3.0", "Violation Capture &\nChallan Issuance"),
        (3.0, 2.4, "4.0", "Payment Processing &\nDynamic QR Generation"),
        (7.0, 2.4, "5.0", "Tax Invoice & Digital\nReceipt Generation"),
        (10.2, 2.4, "6.0", "Audit Reporting &\nCSV Export")
    ]

    for px, py, pid, ptitle in processes:
        box = FancyBboxPatch((px, py), 2.2, 1.2, boxstyle="round,pad=0.02,rounding_size=0.08",
                             facecolor='#FEF3C7', edgecolor='#D97706', linewidth=1.5, zorder=3)
        ax.add_patch(box)
        ax.text(px + 1.1, py + 0.85, pid, ha='center', va='center', fontsize=10, fontweight='bold', color='#92400E', zorder=4)
        ax.text(px + 1.1, py + 0.42, ptitle, ha='center', va='center', fontsize=8, fontweight='bold', color='#78350F', zorder=4)

    # Data Stores (Open-ended parallel lines)
    def draw_datastore(x, y, w, h, ds_id, name):
        ax.plot([x, x + w], [y + h, y + h], color='#0284C7', linewidth=2, zorder=3)
        ax.plot([x, x + w], [y, y], color='#0284C7', linewidth=2, zorder=3)
        ax.fill_between([x, x + w], y, y + h, color='#F0F9FF', zorder=2)
        ax.text(x + 0.3, y + h/2, ds_id, ha='center', va='center', fontsize=8.5, fontweight='bold', color='#0369A1', zorder=4)
        ax.plot([x + 0.6, x + 0.6], [y, y + h], color='#0284C7', linewidth=1, zorder=3)
        ax.text(x + 0.7 + (w - 0.7)/2, y + h/2, name, ha='center', va='center', fontsize=8.5, fontweight='bold', color='#0369A1', zorder=4)

    draw_datastore(0.5, 4.4, 2.4, 0.6, "D1", "Users Store")
    draw_datastore(4.6, 4.4, 2.8, 0.6, "D2", "Challans Store")
    draw_datastore(8.7, 4.4, 2.8, 0.6, "D3", "Payments Store")

    # Connect 1.0 to D1
    draw_arrow(ax, 2.1, 6.0, 1.7, 5.0, label="Verify Credentials", label_offset=(-0.6, 0))
    # Connect 2.0 to D2
    draw_arrow(ax, 5.9, 6.0, 5.9, 5.0, label="Fetch Challans by Plate", label_offset=(0.8, 0))
    # Connect 3.0 to D2
    draw_arrow(ax, 9.7, 6.0, 7.4, 4.8, label="Store New Challan Record", label_offset=(0, 0.2))
    # Connect 4.0 to D2 and D3
    draw_arrow(ax, 4.1, 3.6, 5.3, 4.4, label="Update Status = Paid", label_offset=(-0.4, 0.2))
    draw_arrow(ax, 5.2, 3.0, 8.7, 4.5, label="Insert Payment Record", label_offset=(0.5, -0.2))
    # Connect 5.0 to D2 and D3
    draw_arrow(ax, 7.8, 3.6, 6.8, 4.4, label="Read Challan Details", label_offset=(-0.3, 0))
    draw_arrow(ax, 8.5, 3.6, 9.6, 4.4, label="Read Payment Txn", label_offset=(0.4, 0))
    # Connect 6.0 to D2
    draw_arrow(ax, 11.0, 3.6, 7.4, 4.5, label="Aggregate Challans", label_offset=(0.8, 0.2))

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_4_dfd_level_1.png"), bbox_inches='tight')
    plt.close()

# 5. DFD LEVEL 2 (CHALLAN & PAYMENT SUBSYSTEM)
def gen_dfd_2():
    fig, ax = plt.subplots(figsize=(11, 7.5), dpi=300)
    ax.set_xlim(0, 11)
    ax.set_ylim(0, 7.5)
    ax.axis('off')

    ax.text(5.5, 7.1, "Figure 4.5: Data Flow Diagram (DFD Level 2 - Challan & Payment Pipeline)", ha='center', va='center', fontsize=11, fontweight='bold', color='#0F172A')

    # Sub-processes of 3.0 and 4.0
    sub_procs = [
        (0.8, 5.2, "3.1", "Validate Plate &\nVehicle Linkage"),
        (4.3, 5.2, "3.2", "Assign Violation Code\n& Legal Section"),
        (7.8, 5.2, "3.3", "Upload Evidence &\nCommit Challan"),
        (0.8, 2.0, "4.1", "Encode Dynamic\nUPI Payload & QR"),
        (4.3, 2.0, "4.2", "Process Bank\nSettlement Callback"),
        (7.8, 2.0, "4.3", "Generate Ledger Entry\n& Tax Receipt")
    ]

    for px, py, pid, ptitle in sub_procs:
        box = FancyBboxPatch((px, py), 2.4, 1.1, boxstyle="round,pad=0.02,rounding_size=0.06",
                             facecolor='#FEF3C7', edgecolor='#D97706', linewidth=1.5, zorder=3)
        ax.add_patch(box)
        ax.text(px + 1.2, py + 0.8, pid, ha='center', va='center', fontsize=9.5, fontweight='bold', color='#92400E', zorder=4)
        ax.text(px + 1.2, py + 0.4, ptitle, ha='center', va='center', fontsize=8, fontweight='bold', color='#78350F', zorder=4)

    # Connections between 3.1 -> 3.2 -> 3.3
    draw_arrow(ax, 3.2, 5.75, 4.3, 5.75, label="Verified Vehicle", label_offset=(0, 0.15))
    draw_arrow(ax, 6.7, 5.75, 7.8, 5.75, label="Assigned Fine", label_offset=(0, 0.15))

    # Connection between 4.1 -> 4.2 -> 4.3
    draw_arrow(ax, 3.2, 2.55, 4.3, 2.55, label="QR Scanned & Paid", label_offset=(0, 0.15))
    draw_arrow(ax, 6.7, 2.55, 7.8, 2.55, label="Payment Confirmed", label_offset=(0, 0.15))

    # Bridge between 3.3 and 4.1 (Challan ready for payment)
    draw_arrow(ax, 9.0, 5.2, 2.0, 3.1, label="Unpaid Challan Generated", label_offset=(0, 0.15), rad=0.2)

    # Data store bar in middle
    ax.plot([3.5, 7.5], [4.1, 4.1], color='#0284C7', linewidth=2, zorder=3)
    ax.plot([3.5, 7.5], [3.6, 3.6], color='#0284C7', linewidth=2, zorder=3)
    ax.fill_between([3.5, 7.5], 3.6, 4.1, color='#F0F9FF', zorder=2)
    ax.text(5.5, 3.85, "D2: Challans Repository (InnoDB Table)", ha='center', va='center', fontsize=8.5, fontweight='bold', color='#0369A1', zorder=4)

    draw_arrow(ax, 8.5, 5.2, 6.8, 4.1, label="Store Record", label_offset=(0.4, 0))
    draw_arrow(ax, 4.5, 4.1, 2.5, 3.1, label="Fetch Fine Amount", label_offset=(-0.4, 0))
    draw_arrow(ax, 5.5, 3.1, 5.5, 3.6, label="Update Status = Paid", label_offset=(0.7, 0))

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_5_dfd_level_2.png"), bbox_inches='tight')
    plt.close()

# 6. ER DIAGRAM
def gen_er_diagram():
    fig, ax = plt.subplots(figsize=(12, 8.5), dpi=300)
    ax.set_xlim(0, 12)
    ax.set_ylim(0, 8.5)
    ax.axis('off')

    ax.text(6.0, 8.1, "Figure 4.6: Entity-Relationship (ER) Diagram", ha='center', va='center', fontsize=12, fontweight='bold', color='#0F172A')

    # Entities
    # 1. USER
    draw_box(ax, 0.8, 5.0, 2.6, 2.2, "USERS (ENTITY)", subtext="• PK: id (INT)\n• email (VARCHAR)\n• vehicle_no (VARCHAR)\n• password (VARCHAR)\n• role (ENUM)\n• created_at (TIMESTAMP)", bg_color='#EFF6FF', border_color='#1E40AF', bold=True, fontsize=9)

    # 2. CHALLAN
    draw_box(ax, 4.8, 4.4, 3.0, 3.2, "CHALLANS (ENTITY)", subtext="• PK: id (INT)\n• challan_no (VARCHAR)\n• FK: user_id (INT)\n• vehicle_no (VARCHAR)\n• violation_date (DATETIME)\n• violation (VARCHAR)\n• violation_desc (TEXT)\n• fine_amount (DECIMAL)\n• status (ENUM)\n• evidence_photo (VARCHAR)\n• location (VARCHAR)\n• officer_name (VARCHAR)", bg_color='#EFF6FF', border_color='#1E40AF', bold=True, fontsize=8.5)

    # 3. PAYMENT
    draw_box(ax, 9.2, 5.0, 2.4, 2.2, "PAYMENTS (ENTITY)", subtext="• PK: id (INT)\n• FK: challan_id (INT)\n• amount (DECIMAL)\n• paid_at (TIMESTAMP)", bg_color='#EFF6FF', border_color='#1E40AF', bold=True, fontsize=9)

    # 4. VIOLATION RULES (Lookup Entity)
    draw_box(ax, 1.0, 1.2, 2.8, 2.2, "VIOLATION_RULES", subtext="• violation_code (VARCHAR)\n• legal_act_section (TEXT)\n• default_fine (DECIMAL)\n• category (VARCHAR)\n• cmvr_rule (VARCHAR)", bg_color='#F0FDF4', border_color='#15803D', bold=True, fontsize=8.5)

    # 5. RTO REGISTRY (Lookup Entity)
    draw_box(ax, 5.0, 1.2, 2.8, 2.2, "RTO_VEHICLE_SPECS", subtext="• state_code (VARCHAR)\n• rto_authority (VARCHAR)\n• vehicle_class (VARCHAR)\n• fuel_type (VARCHAR)\n• insurance_status (VARCHAR)\n• pucc_status (VARCHAR)", bg_color='#F0FDF4', border_color='#15803D', bold=True, fontsize=8.5)

    # 6. DIGITAL RECEIPT (Derived Entity)
    draw_box(ax, 8.8, 1.2, 2.8, 2.2, "TAX_RECEIPTS", subtext="• receipt_no (VARCHAR)\n• challan_no (VARCHAR)\n• transaction_ref (VARCHAR)\n• tax_jurisdiction (VARCHAR)\n• generated_at (TIMESTAMP)", bg_color='#F0FDF4', border_color='#15803D', bold=True, fontsize=8.5)

    # Relationships (Diamonds)
    def draw_diamond(x, y, w, h, text):
        poly = Polygon([[x, y + h/2], [x + w/2, y + h], [x + w, y + h/2], [x + w/2, y]],
                       facecolor='#FEF3C7', edgecolor='#D97706', linewidth=1.5, zorder=3)
        ax.add_patch(poly)
        ax.text(x + w/2, y + h/2, text, ha='center', va='center', fontsize=8, fontweight='bold', color='#78350F', zorder=4)

    draw_diamond(3.6, 5.7, 1.0, 0.8, "Owns /\nIncurs")
    draw_diamond(8.0, 5.7, 1.0, 0.8, "Settles /\nPays")
    draw_diamond(4.8, 3.2, 1.0, 0.8, "Enforces")
    draw_diamond(6.8, 3.2, 1.0, 0.8, "Verifies")
    draw_diamond(9.5, 3.7, 1.0, 0.8, "Produces")

    # Connect lines with Cardinality
    # USERS -> Owns -> CHALLANS
    ax.plot([3.4, 3.6], [6.1, 6.1], color='#334155', linewidth=1.5)
    ax.text(3.45, 6.25, "1", fontsize=9, fontweight='bold', color='#1E293B')
    ax.plot([4.6, 4.8], [6.1, 6.1], color='#334155', linewidth=1.5)
    ax.text(4.7, 6.25, "N", fontsize=9, fontweight='bold', color='#1E293B')

    # CHALLANS -> Settles -> PAYMENTS
    ax.plot([7.8, 8.0], [6.1, 6.1], color='#334155', linewidth=1.5)
    ax.text(7.85, 6.25, "1", fontsize=9, fontweight='bold', color='#1E293B')
    ax.plot([9.0, 9.2], [6.1, 6.1], color='#334155', linewidth=1.5)
    ax.text(9.1, 6.25, "1", fontsize=9, fontweight='bold', color='#1E293B')

    # VIOLATION_RULES -> Enforces -> CHALLANS
    ax.plot([2.4, 4.8], [3.4, 3.6], color='#334155', linewidth=1.5)
    ax.plot([5.8, 6.0], [3.6, 4.4], color='#334155', linewidth=1.5)

    # RTO_VEHICLE_SPECS -> Verifies -> CHALLANS
    ax.plot([6.4, 7.3], [3.4, 3.6], color='#334155', linewidth=1.5)
    ax.plot([7.3, 6.8], [4.0, 4.4], color='#334155', linewidth=1.5)

    # PAYMENTS -> Produces -> TAX_RECEIPTS
    ax.plot([10.0, 10.0], [5.0, 4.5], color='#334155', linewidth=1.5)
    ax.plot([10.0, 10.0], [3.7, 3.4], color='#334155', linewidth=1.5)

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_6_er_diagram.png"), bbox_inches='tight')
    plt.close()

# 7. ACTIVITY DIAGRAM
def gen_activity_diagram():
    fig, ax = plt.subplots(figsize=(12, 8), dpi=300)
    ax.set_xlim(0, 12)
    ax.set_ylim(0, 8)
    ax.axis('off')

    ax.text(6.0, 7.7, "Figure 4.7: System Activity Diagram (End-to-End e-Challan Workflow)", ha='center', va='center', fontsize=12, fontweight='bold', color='#0F172A')

    # Swimlanes
    # Lane 1: Traffic Police / ANPR Camera
    ax.plot([0.5, 0.5], [0.5, 7.3], color='#94A3B8', linewidth=1.5)
    ax.plot([4.2, 4.2], [0.5, 7.3], color='#94A3B8', linewidth=1.5)
    ax.text(2.35, 7.1, "ENFORCEMENT (POLICE / ANPR)", ha='center', va='center', fontsize=9.5, fontweight='bold', color='#1E40AF')

    # Lane 2: Core Web System
    ax.plot([8.0, 8.0], [0.5, 7.3], color='#94A3B8', linewidth=1.5)
    ax.text(6.1, 7.1, "CENTRAL PORTAL ENGINE", ha='center', va='center', fontsize=9.5, fontweight='bold', color='#065F46')

    # Lane 3: Citizen / Road User
    ax.plot([11.5, 11.5], [0.5, 7.3], color='#94A3B8', linewidth=1.5)
    ax.text(9.75, 7.1, "CITIZEN / VEHICLE OWNER", ha='center', va='center', fontsize=9.5, fontweight='bold', color='#92400E')

    # Initial Node (Black circle)
    init_c = Circle((2.35, 6.5), 0.16, facecolor='#0F172A', edgecolor='#0F172A', zorder=4)
    ax.add_patch(init_c)

    # Activity 1: Detect Violation
    draw_box(ax, 1.1, 5.4, 2.5, 0.7, "Detect Traffic Violation\n& Record Vehicle No", bg_color='#EFF6FF', border_color='#2563EB', bold=True, fontsize=8)
    draw_arrow(ax, 2.35, 6.34, 2.35, 6.1)

    # Activity 2: Upload Evidence Photos
    draw_box(ax, 1.1, 4.2, 2.5, 0.7, "Capture Dual-Cam Evidence\n& Junction Telemetry", bg_color='#EFF6FF', border_color='#2563EB', bold=True, fontsize=8)
    draw_arrow(ax, 2.35, 5.4, 2.35, 4.9)

    # Activity 3: Submit to Portal
    draw_arrow(ax, 3.6, 4.55, 4.8, 4.55, label="save_challan.php")

    # Portal Activity: Create Challan Record
    draw_box(ax, 4.8, 4.2, 2.5, 0.7, "Generate Challan No\n& Persist in Database", bg_color='#ECFDF5', border_color='#059669', bold=True, fontsize=8)

    # Citizen Activity: Query Vehicle
    draw_box(ax, 8.5, 5.4, 2.5, 0.7, "Enter Vehicle Number\nin Enquiry Portal", bg_color='#FFFBEB', border_color='#D97706', bold=True, fontsize=8)

    # Portal Activity: Return Challans & Photos
    draw_arrow(ax, 8.5, 5.75, 7.3, 5.75, label="Query Request")
    draw_box(ax, 4.8, 5.4, 2.5, 0.7, "Lookup RTO Details &\nActive Pending Challans", bg_color='#ECFDF5', border_color='#059669', bold=True, fontsize=8)
    draw_arrow(ax, 6.05, 5.4, 6.05, 4.9)
    draw_arrow(ax, 7.3, 5.5, 8.5, 5.5, label="Render Evidence")

    # Citizen Activity: Inspect Evidence & Pay
    draw_box(ax, 8.5, 4.2, 2.5, 0.7, "Inspect Evidence Photos\n& Click 'Pay Fine'", bg_color='#FFFBEB', border_color='#D97706', bold=True, fontsize=8)

    # Portal Activity: Render Dynamic QR
    draw_box(ax, 4.8, 2.9, 2.5, 0.7, "Encode UPI Intent String\n& Render Dynamic QR", bg_color='#ECFDF5', border_color='#059669', bold=True, fontsize=8)
    draw_arrow(ax, 8.5, 4.3, 7.3, 3.25, label="Initiate Checkout")

    # Citizen Activity: Complete Payment via UPI
    draw_box(ax, 8.5, 2.9, 2.5, 0.7, "Scan QR via GPay/PhonePe\n& Complete Settlement", bg_color='#FFFBEB', border_color='#D97706', bold=True, fontsize=8)
    draw_arrow(ax, 7.3, 3.25, 8.5, 3.25)
    draw_arrow(ax, 9.75, 2.9, 9.75, 2.2)

    # Citizen Activity: Download Receipt
    draw_box(ax, 8.5, 1.5, 2.5, 0.7, "Download Official\nPDF Tax Invoice", bg_color='#FFFBEB', border_color='#D97706', bold=True, fontsize=8)

    # Portal Activity: Update Status & Log
    draw_box(ax, 4.8, 1.5, 2.5, 0.7, "Update Status='Paid'\n& Issue Digital Receipt", bg_color='#ECFDF5', border_color='#059669', bold=True, fontsize=8)
    draw_arrow(ax, 8.5, 1.85, 7.3, 1.85, label="Verify Payment")

    # Final Node (Bullseye)
    final_out = Circle((9.75, 0.9), 0.18, facecolor='none', edgecolor='#0F172A', linewidth=1.5, zorder=4)
    final_in = Circle((9.75, 0.9), 0.11, facecolor='#0F172A', edgecolor='#0F172A', zorder=5)
    ax.add_patch(final_out)
    ax.add_patch(final_in)
    draw_arrow(ax, 9.75, 1.5, 9.75, 1.1)

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_7_activity_diagram.png"), bbox_inches='tight')
    plt.close()

# 8. SEQUENCE DIAGRAM
def gen_sequence_diagram():
    fig, ax = plt.subplots(figsize=(12, 8), dpi=300)
    ax.set_xlim(0, 12)
    ax.set_ylim(0, 8.5)
    ax.axis('off')

    ax.text(6.0, 8.1, "Figure 4.8: System Sequence Diagram (Vehicle Inquiry to Payment & Receipt)", ha='center', va='center', fontsize=12, fontweight='bold', color='#0F172A')

    # Lifelines
    lifelines = [
        (1.2, "Citizen / User\n(Client Browser)"),
        (3.8, "Enquiry & Dashboard\n(PHP Controller)"),
        (6.4, "RTO & Intelligence\n(challan_helper.php)"),
        (9.0, "Relational DB\n(MySQL RDBMS)"),
        (11.0, "UPI / Payment\nGateway")
    ]

    for lx, ltitle in lifelines:
        draw_box(ax, lx - 0.9, 7.1, 1.8, 0.7, ltitle, bg_color='#EFF6FF', border_color='#1E40AF', bold=True, fontsize=7.5)
        ax.plot([lx, lx], [0.8, 7.1], color='#94A3B8', linestyle='--', linewidth=1.2, zorder=1)

    # Sequence Messages
    msgs = [
        (1.2, 3.8, 6.6, "1: GET vehicle_enquiry.php?no=KL07AB1234"),
        (3.8, 6.4, 6.1, "2: getVehicleDetails('KL07AB1234')"),
        (6.4, 3.8, 5.7, "3: return vehicle specs, RTO, insurance"),
        (3.8, 9.0, 5.2, "4: SELECT * FROM challans WHERE vehicle_no=..."),
        (9.0, 3.8, 4.7, "5: return active challan records & evidence paths"),
        (3.8, 1.2, 4.3, "6: Render Dashboard with Violation Photos & Fine"),
        (1.2, 3.8, 3.8, "7: POST pay.php (Challan ID, CSRF Token)"),
        (3.8, 11.0, 3.3, "8: Generate UPI Intent String (upi://pay?pa=...)"),
        (11.0, 1.2, 2.9, "9: Render Dynamic QRCode on Modal"),
        (1.2, 11.0, 2.4, "10: User scans QR & Authorizes UPI PIN"),
        (11.0, 3.8, 2.0, "11: Payment Callback Verified (Txn Success)"),
        (3.8, 9.0, 1.6, "12: UPDATE challans SET status='Paid' & INSERT payments"),
        (3.8, 1.2, 1.1, "13: Redirect to receipt.php?id=... (Print Tax Invoice)")
    ]

    for x1, x2, y, txt in msgs:
        draw_arrow(ax, x1, y, x2, y, label=txt, label_offset=(0, 0.12), fontsize=7.5)

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_8_sequence_diagram.png"), bbox_inches='tight')
    plt.close()

# 9. CLASS DIAGRAM
def gen_class_diagram():
    fig, ax = plt.subplots(figsize=(12, 8.5), dpi=300)
    ax.set_xlim(0, 12)
    ax.set_ylim(0, 8.5)
    ax.axis('off')

    ax.text(6.0, 8.1, "Figure 4.9: Object-Oriented Class & Module Architecture Diagram", ha='center', va='center', fontsize=12, fontweight='bold', color='#0F172A')

    # Class 1: DatabaseManager
    draw_box(ax, 0.6, 5.0, 3.0, 2.4, "DatabaseManager",
             subtext="––––––––––––––––––––––\n- conn: mysqli\n- host, user, pass, db: string\n––––––––––––––––––––––\n+ __construct()\n+ query(sql: string): result\n+ escape(val: string): string\n+ close(): void",
             bg_color='#F8FAFC', border_color='#0F172A', bold=True, fontsize=8)

    # Class 2: User
    draw_box(ax, 4.5, 5.0, 3.2, 2.4, "User",
             subtext="––––––––––––––––––––––\n- id: int\n- email: string\n- vehicle_no: string\n- role: string\n––––––––––––––––––––––\n+ login(email, pass): bool\n+ register(email, vno, pass): bool\n+ getRegisteredVehicles(): array",
             bg_color='#F8FAFC', border_color='#0F172A', bold=True, fontsize=8)

    # Class 3: Admin (Inherits from User)
    draw_box(ax, 8.6, 5.0, 2.8, 2.4, "AdminController",
             subtext="––––––––––––––––––––––\n- adminLevel: int\n- stationBadge: string\n––––––––––––––––––––––\n+ getOverviewMetrics(): array\n+ exportCSV(): file\n+ getGeoTelemetry(): json",
             bg_color='#F8FAFC', border_color='#0F172A', bold=True, fontsize=8)

    # Class 4: ChallanModel
    draw_box(ax, 0.6, 1.4, 3.2, 2.8, "ChallanModel",
             subtext="––––––––––––––––––––––\n- id: int\n- challan_no: string\n- vehicle_no: string\n- violation: string\n- fine_amount: float\n- status: string\n- evidence_photo: string\n––––––––––––––––––––––\n+ createChallan(): bool\n+ getByVehicle(vno): array\n+ markPaid(id): bool\n+ deleteChallan(id): bool",
             bg_color='#F8FAFC', border_color='#0F172A', bold=True, fontsize=8)

    # Class 5: VehicleIntelligenceHelper
    draw_box(ax, 4.5, 1.4, 3.2, 2.8, "VehicleIntelligenceHelper",
             subtext="––––––––––––––––––––––\n- cleanPlate: string\n- rtoCatalog: array\n- statutoryRules: array\n––––––––––––––––––––––\n+ getVehicleDetails(vno): array\n+ getViolationLegalDetails(v): array\n+ resolveRTO(rtoCode): string\n+ validatePUCC(vno): string",
             bg_color='#F8FAFC', border_color='#0F172A', bold=True, fontsize=8)

    # Class 6: PaymentAndInvoiceEngine
    draw_box(ax, 8.4, 1.4, 3.0, 2.8, "PaymentAndInvoiceEngine",
             subtext="––––––––––––––––––––––\n- challanId: int\n- merchantUPI: string\n- receiptNumber: string\n––––––––––––––––––––––\n+ generateUPIString(): string\n+ recordPayment(): int\n+ renderTaxReceipt(): html\n+ exportPDFInvoice(): file",
             bg_color='#F8FAFC', border_color='#0F172A', bold=True, fontsize=8)

    # Associations
    draw_arrow(ax, 3.6, 6.2, 4.5, 6.2, label="1..1 maintains 0..*", fontsize=7)
    draw_arrow(ax, 7.7, 6.2, 8.6, 6.2, label="manages", fontsize=7)
    draw_arrow(ax, 2.1, 5.0, 2.1, 4.2, label="persists", fontsize=7)
    draw_arrow(ax, 3.8, 2.8, 4.5, 2.8, label="enriches", fontsize=7)
    draw_arrow(ax, 7.7, 2.8, 8.4, 2.8, label="settles", fontsize=7)

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_9_class_diagram.png"), bbox_inches='tight')
    plt.close()

# 10. LOGIN FLOWCHART
def gen_login_flowchart():
    fig, ax = plt.subplots(figsize=(8, 9), dpi=300)
    ax.set_xlim(0, 8)
    ax.set_ylim(0, 9)
    ax.axis('off')

    ax.text(4.0, 8.6, "Figure 4.10: Authentication & Role Verification Flowchart", ha='center', va='center', fontsize=11, fontweight='bold', color='#0F172A')

    # Shapes: Oval = Start/End, Rect = Process, Diamond = Decision
    def draw_oval(x, y, w, h, text, bg='#EFF6FF'):
        box = FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.02,rounding_size=0.3",
                             facecolor=bg, edgecolor='#1E40AF', linewidth=1.5, zorder=3)
        ax.add_patch(box)
        ax.text(x + w/2, y + h/2, text, ha='center', va='center', fontsize=9, fontweight='bold', color='#1E293B', zorder=4)

    def draw_decision(x, y, w, h, text):
        poly = Polygon([[x, y + h/2], [x + w/2, y + h], [x + w, y + h/2], [x + w/2, y]],
                       facecolor='#FEF3C7', edgecolor='#D97706', linewidth=1.5, zorder=3)
        ax.add_patch(poly)
        ax.text(x + w/2, y + h/2, text, ha='center', va='center', fontsize=8, fontweight='bold', color='#78350F', zorder=4)

    draw_oval(3.0, 7.8, 2.0, 0.6, "START")
    draw_box(ax, 2.5, 6.7, 3.0, 0.7, "Enter Email, Password\n& Role (Admin / Citizen)", bg_color='#F1F5F9', border_color='#475569', bold=True)
    draw_arrow(ax, 4.0, 7.8, 4.0, 7.4)

    draw_box(ax, 2.5, 5.6, 3.0, 0.7, "Validate CSRF Token\n& Sanitize Input Strings", bg_color='#F1F5F9', border_color='#475569', bold=True)
    draw_arrow(ax, 4.0, 6.7, 4.0, 6.3)

    draw_decision(2.4, 4.2, 3.2, 1.0, "Credentials & Password\nHash Match in DB?")
    draw_arrow(ax, 4.0, 5.6, 4.0, 5.2)

    # No branch
    draw_box(ax, 6.0, 4.3, 1.8, 0.8, "Display Error Alert\n& Log Attempt", bg_color='#FEE2E2', border_color='#DC2626', bold=True, fontsize=8)
    draw_arrow(ax, 5.6, 4.7, 6.0, 4.7, label="No")
    draw_arrow(ax, 6.9, 5.1, 4.0, 6.9, label="Retry", rad=-0.4)

    # Yes branch
    draw_decision(2.4, 2.6, 3.2, 1.0, "Is User Role\n'admin'?")
    draw_arrow(ax, 4.0, 4.2, 4.0, 3.6, label="Yes")

    # Admin branch
    draw_box(ax, 0.4, 1.4, 2.8, 0.8, "Create Admin Session\nRedirect to admin_dashboard.php", bg_color='#DCFCE7', border_color='#16A34A', bold=True, fontsize=7.5)
    draw_arrow(ax, 2.4, 3.1, 1.8, 2.2, label="Yes")

    # User branch
    draw_box(ax, 4.8, 1.4, 2.8, 0.8, "Create User Session\nRedirect to user_dashboard.php", bg_color='#DCFCE7', border_color='#16A34A', bold=True, fontsize=7.5)
    draw_arrow(ax, 5.6, 3.1, 6.2, 2.2, label="No (User)")

    draw_oval(3.0, 0.3, 2.0, 0.6, "END")
    draw_arrow(ax, 1.8, 1.4, 3.2, 0.7)
    draw_arrow(ax, 6.2, 1.4, 4.8, 0.7)

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_10_login_flowchart.png"), bbox_inches='tight')
    plt.close()

# 11. VEHICLE INQUIRY FLOWCHART
def gen_vehicle_inquiry_flowchart():
    fig, ax = plt.subplots(figsize=(8, 9), dpi=300)
    ax.set_xlim(0, 8)
    ax.set_ylim(0, 9)
    ax.axis('off')

    ax.text(4.0, 8.6, "Figure 4.11: Vehicle Inquiry & RTO Lookup Flowchart", ha='center', va='center', fontsize=11, fontweight='bold', color='#0F172A')

    def draw_oval(x, y, w, h, text, bg='#EFF6FF'):
        box = FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.02,rounding_size=0.3",
                             facecolor=bg, edgecolor='#1E40AF', linewidth=1.5, zorder=3)
        ax.add_patch(box)
        ax.text(x + w/2, y + h/2, text, ha='center', va='center', fontsize=9, fontweight='bold', color='#1E293B', zorder=4)

    def draw_decision(x, y, w, h, text):
        poly = Polygon([[x, y + h/2], [x + w/2, y + h], [x + w, y + h/2], [x + w/2, y]],
                       facecolor='#FEF3C7', edgecolor='#D97706', linewidth=1.5, zorder=3)
        ax.add_patch(poly)
        ax.text(x + w/2, y + h/2, text, ha='center', va='center', fontsize=8, fontweight='bold', color='#78350F', zorder=4)

    draw_oval(3.0, 7.8, 2.0, 0.6, "START")
    draw_box(ax, 2.2, 6.8, 3.6, 0.65, "User inputs Vehicle Plate Number\n(e.g., 'KL 07 AB 1234')", bg_color='#F1F5F9', border_color='#475569', bold=True)
    draw_arrow(ax, 4.0, 7.8, 4.0, 7.45)

    draw_box(ax, 2.2, 5.8, 3.6, 0.65, "Sanitize & Strip Formatting\ncleanNo = preg_replace('/[^A-Z0-9]/', '')", bg_color='#F1F5F9', border_color='#475569', bold=True)
    draw_arrow(ax, 4.0, 6.8, 4.0, 6.45)

    draw_decision(2.2, 4.4, 3.6, 1.0, "Is Plate Format\nSyntactically Valid?")
    draw_arrow(ax, 4.0, 5.8, 4.0, 5.4)

    draw_box(ax, 6.0, 4.5, 1.8, 0.8, "Alert: Invalid\nRegistration Format", bg_color='#FEE2E2', border_color='#DC2626', bold=True, fontsize=7.5)
    draw_arrow(ax, 5.8, 4.9, 6.0, 4.9, label="No")

    draw_box(ax, 2.0, 3.2, 4.0, 0.8, "Invoke getVehicleDetails(cleanNo)\nResolve RTO, Make, Model, Fuel, PUCC", bg_color='#EFF6FF', border_color='#2563EB', bold=True)
    draw_arrow(ax, 4.0, 4.4, 4.0, 4.0, label="Yes")

    draw_box(ax, 2.0, 2.0, 4.0, 0.8, "Query MySQL 'challans' Table\nFilter by vehicle_no & Status", bg_color='#ECFDF5', border_color='#059669', bold=True)
    draw_arrow(ax, 4.0, 3.2, 4.0, 2.8)

    draw_box(ax, 2.0, 0.9, 4.0, 0.8, "Render Digital RC Specs Card,\nViolations List & Evidence Images", bg_color='#DCFCE7', border_color='#16A34A', bold=True)
    draw_arrow(ax, 4.0, 2.0, 4.0, 1.7)

    draw_oval(3.0, 0.1, 2.0, 0.5, "END")
    draw_arrow(ax, 4.0, 0.9, 4.0, 0.6)

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_11_vehicle_inquiry_flowchart.png"), bbox_inches='tight')
    plt.close()

# 12. CHALLAN GENERATION FLOWCHART
def gen_challan_generation_flowchart():
    fig, ax = plt.subplots(figsize=(8, 9), dpi=300)
    ax.set_xlim(0, 8)
    ax.set_ylim(0, 9)
    ax.axis('off')

    ax.text(4.0, 8.6, "Figure 4.12: Challan Generation & Evidence Capture Flowchart", ha='center', va='center', fontsize=11, fontweight='bold', color='#0F172A')

    def draw_oval(x, y, w, h, text, bg='#EFF6FF'):
        box = FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.02,rounding_size=0.3",
                             facecolor=bg, edgecolor='#1E40AF', linewidth=1.5, zorder=3)
        ax.add_patch(box)
        ax.text(x + w/2, y + h/2, text, ha='center', va='center', fontsize=9, fontweight='bold', color='#1E293B', zorder=4)

    draw_oval(3.0, 7.8, 2.0, 0.6, "START")

    draw_box(ax, 2.0, 6.7, 4.0, 0.7, "Traffic Officer opens 'Issue Challan' Modal\nEnter Vehicle No & Select Violation Type", bg_color='#F1F5F9', border_color='#475569', bold=True)
    draw_arrow(ax, 4.0, 7.8, 4.0, 7.4)

    draw_box(ax, 2.0, 5.5, 4.0, 0.8, "Auto-Populate Legal Provision & Fine Amount\n(Section 129, 112, 184 MVA via legal catalog)", bg_color='#EFF6FF', border_color='#2563EB', bold=True)
    draw_arrow(ax, 4.0, 6.7, 4.0, 6.3)

    draw_box(ax, 2.0, 4.3, 4.0, 0.8, "Upload Front & Side Angle Photographic Evidence\n(Dual Camera ANPR Telemetry & Timestamp)", bg_color='#EFF6FF', border_color='#2563EB', bold=True)
    draw_arrow(ax, 4.0, 5.5, 4.0, 5.1)

    draw_box(ax, 2.0, 3.1, 4.0, 0.8, "Generate Unique Challan Number:\nKL-CHN-[YEAR]-[ZERO_PADDED_ID]", bg_color='#ECFDF5', border_color='#059669', bold=True)
    draw_arrow(ax, 4.0, 4.3, 4.0, 3.9)

    draw_box(ax, 2.0, 1.9, 4.0, 0.8, "Execute Prepared INSERT Query into 'challans'\nSet Status = 'Unpaid', Due Date = NOW + 60 Days", bg_color='#ECFDF5', border_color='#059669', bold=True)
    draw_arrow(ax, 4.0, 3.1, 4.0, 2.7)

    draw_box(ax, 2.0, 0.9, 4.0, 0.65, "Send Notification / Broadcast to Citizen Ledger", bg_color='#DCFCE7', border_color='#16A34A', bold=True)
    draw_arrow(ax, 4.0, 1.9, 4.0, 1.55)

    draw_oval(3.0, 0.1, 2.0, 0.5, "END")
    draw_arrow(ax, 4.0, 0.9, 4.0, 0.6)

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_12_challan_generation_flowchart.png"), bbox_inches='tight')
    plt.close()

# 13. PAYMENT & QR FLOWCHART
def gen_payment_flowchart():
    fig, ax = plt.subplots(figsize=(8, 9), dpi=300)
    ax.set_xlim(0, 8)
    ax.set_ylim(0, 9)
    ax.axis('off')

    ax.text(4.0, 8.6, "Figure 4.13: Dynamic UPI QR Code & Payment Settlement Flowchart", ha='center', va='center', fontsize=11, fontweight='bold', color='#0F172A')

    def draw_oval(x, y, w, h, text, bg='#EFF6FF'):
        box = FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.02,rounding_size=0.3",
                             facecolor=bg, edgecolor='#1E40AF', linewidth=1.5, zorder=3)
        ax.add_patch(box)
        ax.text(x + w/2, y + h/2, text, ha='center', va='center', fontsize=9, fontweight='bold', color='#1E293B', zorder=4)

    def draw_decision(x, y, w, h, text):
        poly = Polygon([[x, y + h/2], [x + w/2, y + h], [x + w, y + h/2], [x + w/2, y]],
                       facecolor='#FEF3C7', edgecolor='#D97706', linewidth=1.5, zorder=3)
        ax.add_patch(poly)
        ax.text(x + w/2, y + h/2, text, ha='center', va='center', fontsize=8, fontweight='bold', color='#78350F', zorder=4)

    draw_oval(3.0, 7.8, 2.0, 0.6, "START")

    draw_box(ax, 2.0, 6.7, 4.0, 0.7, "User clicks 'Pay Online' for Challan ID\nSystem validates Status == 'Unpaid'", bg_color='#F1F5F9', border_color='#475569', bold=True)
    draw_arrow(ax, 4.0, 7.8, 4.0, 7.4)

    draw_box(ax, 2.0, 5.5, 4.0, 0.8, "Construct UPI Deep Link Intent String:\nupi://pay?pa=mvd@gov&pn=eChallan&am=...&tn=...", bg_color='#EFF6FF', border_color='#2563EB', bold=True)
    draw_arrow(ax, 4.0, 6.7, 4.0, 6.3)

    draw_box(ax, 2.0, 4.3, 4.0, 0.8, "Generate Dynamic High-Resolution QR Code\nRender in Interactive Modal with Real-Time Timer", bg_color='#EFF6FF', border_color='#2563EB', bold=True)
    draw_arrow(ax, 4.0, 5.5, 4.0, 5.1)

    draw_box(ax, 2.0, 3.2, 4.0, 0.7, "Citizen Scans QR via GPay / PhonePe / Paytm\nBank Processes Funds Transfer", bg_color='#FFFBEB', border_color='#D97706', bold=True)
    draw_arrow(ax, 4.0, 4.3, 4.0, 3.9)

    draw_decision(2.2, 1.8, 3.6, 1.0, "Transaction Successful\n& Confirmed?")
    draw_arrow(ax, 4.0, 3.2, 4.0, 2.8)

    draw_box(ax, 6.0, 1.9, 1.8, 0.8, "Transaction Failed\nPrompt Retry", bg_color='#FEE2E2', border_color='#DC2626', bold=True, fontsize=7.5)
    draw_arrow(ax, 5.8, 2.3, 6.0, 2.3, label="No")

    draw_box(ax, 2.0, 0.7, 4.0, 0.8, "Execute DB Transaction:\n1. UPDATE challans SET status='Paid'\n2. INSERT INTO payments (challan_id, amount)", bg_color='#DCFCE7', border_color='#16A34A', bold=True)
    draw_arrow(ax, 4.0, 1.8, 4.0, 1.5, label="Yes")

    draw_oval(3.0, 0.05, 2.0, 0.5, "END")
    draw_arrow(ax, 4.0, 0.7, 4.0, 0.55)

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_13_payment_flowchart.png"), bbox_inches='tight')
    plt.close()

# 14. RECEIPT GENERATION FLOWCHART
def gen_receipt_flowchart():
    fig, ax = plt.subplots(figsize=(8, 9), dpi=300)
    ax.set_xlim(0, 8)
    ax.set_ylim(0, 9)
    ax.axis('off')

    ax.text(4.0, 8.6, "Figure 4.14: Official Tax Invoice & Digital Receipt Flowchart", ha='center', va='center', fontsize=11, fontweight='bold', color='#0F172A')

    def draw_oval(x, y, w, h, text, bg='#EFF6FF'):
        box = FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.02,rounding_size=0.3",
                             facecolor=bg, edgecolor='#1E40AF', linewidth=1.5, zorder=3)
        ax.add_patch(box)
        ax.text(x + w/2, y + h/2, text, ha='center', va='center', fontsize=9, fontweight='bold', color='#1E293B', zorder=4)

    def draw_decision(x, y, w, h, text):
        poly = Polygon([[x, y + h/2], [x + w/2, y + h], [x + w, y + h/2], [x + w/2, y]],
                       facecolor='#FEF3C7', edgecolor='#D97706', linewidth=1.5, zorder=3)
        ax.add_patch(poly)
        ax.text(x + w/2, y + h/2, text, ha='center', va='center', fontsize=8, fontweight='bold', color='#78350F', zorder=4)

    draw_oval(3.0, 7.8, 2.0, 0.6, "START")

    draw_box(ax, 2.0, 6.7, 4.0, 0.7, "User clicks 'Download Official Receipt'\nRequest received at receipt.php?id=[CHALLAN_ID]", bg_color='#F1F5F9', border_color='#475569', bold=True)
    draw_arrow(ax, 4.0, 7.8, 4.0, 7.4)

    draw_decision(2.2, 5.3, 3.6, 1.0, "Is Challan Status\nMarked as 'Paid'?")
    draw_arrow(ax, 4.0, 6.7, 4.0, 6.3)

    draw_box(ax, 6.0, 5.4, 1.8, 0.8, "Redirect to\nPayment Screen", bg_color='#FEE2E2', border_color='#DC2626', bold=True, fontsize=7.5)
    draw_arrow(ax, 5.8, 5.8, 6.0, 5.8, label="No")

    draw_box(ax, 2.0, 3.9, 4.0, 0.9, "Fetch Payment Transaction Details from DB:\n• Payment ID, Timestamp, Amount\n• Vehicle Specs & Statutory Legal Clauses", bg_color='#EFF6FF', border_color='#2563EB', bold=True)
    draw_arrow(ax, 4.0, 5.3, 4.0, 4.8, label="Yes")

    draw_box(ax, 2.0, 2.6, 4.0, 0.9, "Generate Verification QR Code:\nContains Receipt Verification URL\n& Digital Cryptographic Signature", bg_color='#EFF6FF', border_color='#2563EB', bold=True)
    draw_arrow(ax, 4.0, 3.9, 4.0, 3.5)

    draw_box(ax, 2.0, 1.3, 4.0, 0.9, "Render Official Government Tax Invoice:\n• Official Emblems, Watermark, Barcode\n• Officer Seal, Itemized Fines, GST/Exemption", bg_color='#ECFDF5', border_color='#059669', bold=True)
    draw_arrow(ax, 4.0, 2.6, 4.0, 2.2)

    draw_box(ax, 2.0, 0.5, 4.0, 0.6, "Invoke Browser Print / PDF Export Engine", bg_color='#DCFCE7', border_color='#16A34A', bold=True)
    draw_arrow(ax, 4.0, 1.3, 4.0, 1.1)

    draw_oval(3.0, -0.2, 2.0, 0.5, "END")
    draw_arrow(ax, 4.0, 0.5, 4.0, 0.3)

    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "fig4_14_receipt_flowchart.png"), bbox_inches='tight')
    plt.close()

if __name__ == '__main__':
    print("Generating 14 publication-grade diagrams...")
    gen_architecture()
    print("1. Architecture complete.")
    gen_use_case()
    print("2. Use Case complete.")
    gen_dfd_0()
    print("3. DFD 0 complete.")
    gen_dfd_1()
    print("4. DFD 1 complete.")
    gen_dfd_2()
    print("5. DFD 2 complete.")
    gen_er_diagram()
    print("6. ER Diagram complete.")
    gen_activity_diagram()
    print("7. Activity Diagram complete.")
    gen_sequence_diagram()
    print("8. Sequence Diagram complete.")
    gen_class_diagram()
    print("9. Class Diagram complete.")
    gen_login_flowchart()
    print("10. Login Flowchart complete.")
    gen_vehicle_inquiry_flowchart()
    print("11. Vehicle Inquiry Flowchart complete.")
    gen_challan_generation_flowchart()
    print("12. Challan Generation Flowchart complete.")
    gen_payment_flowchart()
    print("13. Payment Flowchart complete.")
    gen_receipt_flowchart()
    print("14. Receipt Flowchart complete.")
    print("All 14 diagrams generated successfully!")
