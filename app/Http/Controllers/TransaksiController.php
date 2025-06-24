<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->transaksis();

        // Filter berdasarkan bulan
        if ($request->has('bulan')) {
            try {
                [$tahun, $bulan] = explode('-', $request->bulan);
                $query->whereYear('created_at', $tahun)
                    ->whereMonth('created_at', $bulan);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Format bulan harus YYYY-MM'], 422);
            }
        }

        // Filter berdasarkan jenis (pemasukan/pengeluaran)
        if ($request->has('jenis')) {
            $jenis = $request->jenis;
            if (!in_array($jenis, ['pemasukan', 'pengeluaran'])) {
                return response()->json(['message' => 'Jenis harus pemasukan atau pengeluaran'], 422);
            }
            $query->where('jenis', $jenis);
        }

        $sortBy = in_array($request->sort_by, ['jumlah', 'created_at']) ? $request->sort_by : 'created_at';
        $sortOrder = in_array($request->sort_order, ['asc', 'desc']) ? $request->sort_order : 'desc';

        $transaksis = $query->orderBy($sortBy, $sortOrder)->paginate(10);

        return response()->json($transaksis);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'jenis' => 'required|in:pemasukan,pengeluaran',
            'keterangan' => 'required',
            'jumlah' => 'required|integer|min:1',
        ]);

        $transaksi = $request->user()->transaksis()->create($validated);

        return response()->json($transaksi, 201);
    }

    public function sisaUang(Request $request)
    {
        $userId = $request->user()->id;

        $pemasukan = Transaksi::where('user_id', $userId)->where('jenis', 'pemasukan')->sum('jumlah');
        $pengeluaran = Transaksi::where('user_id', $userId)->where('jenis', 'pengeluaran')->sum('jumlah');

        return response()->json([
            'pemasukan' => $pemasukan,
            'pengeluaran' => $pengeluaran,
            'sisa' => $pemasukan - $pengeluaran,
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'jenis' => 'required|in:pemasukan,pengeluaran',
            'keterangan' => 'required',
            'jumlah' => 'required|integer|min:1',
        ]);

        $transaksi = $request->user()->transaksis()->findOrFail($id);
        $transaksi->update($validated);

        return response()->json(['message' => 'Transaksi berhasil diupdate', 'data' => $transaksi]);
    }

    public function destroy(Request $request, $id)
    {
        $transaksi = $request->user()->transaksis()->findOrFail($id);
        $transaksi->delete();

        return response()->json(['message' => 'Transaksi berhasil dihapus']);
    }

    public function ringkasanBulanan(Request $request)
    {
        $userId = $request->user()->id;

        $data = Transaksi::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as bulan"),
                DB::raw("SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE 0 END) as pemasukan"),
                DB::raw("SUM(CASE WHEN jenis = 'pengeluaran' THEN jumlah ELSE 0 END) as pengeluaran")
            )
            ->where('user_id', $userId)
            ->groupBy('bulan')
            ->orderBy('bulan', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'bulan' => $item->bulan,
                    'pemasukan' => (int) $item->pemasukan,
                    'pengeluaran' => (int) $item->pengeluaran,
                    'sisa' => (int) $item->pemasukan - (int) $item->pengeluaran,
                ];
            });

        return response()->json($data);
    }
}
